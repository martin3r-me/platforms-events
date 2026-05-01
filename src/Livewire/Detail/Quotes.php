<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Core\Contracts\CatalogArticleResolverInterface;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Models\Event;
use Platform\Events\Models\FlatRateApplication;
use Platform\Events\Models\FlatRateRule;
use Platform\Events\Models\Quote;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Services\ArticlePackageApplicator;
use Platform\Events\Services\ArticleSearchService;
use Platform\Events\Services\FlatRateApplicator;
use Platform\Events\Services\LocationPricingApplicator;
use Platform\Events\Services\MultiSelectHelper;
use Platform\Events\Services\PositionCalculator;
use Platform\Events\Services\PositionValidator;
use Platform\Events\Services\QuoteOrderConverter;
use Platform\Events\Services\SettingsService;
use Platform\Locations\Models\Location;

class Quotes extends Component
{
    public int $eventId;
    public ?int $activeQuoteId = null;
    public ?int $activeItemId = null;

    /**
     * Sichtmodus:
     *  - 'overview'  = alle Tage (Standard)
     *  - 'day'       = ein bestimmter Tag (activeDayId gesetzt)
     *  - 'articles'  = flache Liste aller Artikel ueber alle Tage
     *  - 'editor'    = Positions-Editor fuer activeItemId
     */
    public string $view = 'overview';
    public ?int $activeDayId = null;

    public array $newPosition = [
        'gruppe' => '', 'name' => '', 'anz' => '', 'anz2' => '',
        'uhrzeit' => '', 'bis' => '', 'inhalt' => '', 'gebinde' => '',
        'basis_ek' => 0, 'ek' => 0, 'preis' => 0, 'mwst' => '7%',
        'gesamt' => 0, 'bemerkung' => '',
    ];

    // Mehrfachauswahl fuer Bulk-Delete von Positionen
    public array $selectedPositionUuids = [];

    public bool $showItemModal = false;
    public ?int $itemEventDayId = null;
    public string $itemTyp = 'Speisen';
    public string $itemStatus = 'Entwurf';
    public string $itemBeverageMode = '';

    // Approval-Modal
    public bool $showApprovalModal = false;
    public ?int $approvalQuoteId = null;
    public ?int $approvalApproverId = null;
    public string $approvalComment = '';

    // Package/Vorlage-Picker
    public bool $showPackagePicker = false;
    public string $packageSearch = '';
    public ?int $selectedPackagePreviewId = null;

    // Pauschal-Regel-Picker
    public bool $showFlatRateModal = false;
    public ?int $flatRateTargetItemId = null;

    // Location-Pricing-Picker
    public bool $showLocationPricingModal = false;
    public ?int $locationPricingTargetItemId = null;
    public ?int $locationPricingLocationId = null;
    /** @var array<int,int> Ausgewaehlte Pricing-IDs */
    public array $locationPricingPricingIds = [];
    /** @var array<int,float|string|null> addon_id => qty (null/leer => deaktiviert) */
    public array $locationPricingAddonQtys = [];
    /** @var array<int,string> */
    public array $locationPricingWarnings = [];

    public function openPackagePicker(): void
    {
        $this->packageSearch = '';
        $this->selectedPackagePreviewId = null;
        $this->showPackagePicker = true;
    }

    public function closePackagePicker(): void
    {
        $this->showPackagePicker = false;
        $this->packageSearch = '';
        $this->selectedPackagePreviewId = null;
    }

    public function selectPackagePreview(int $id): void
    {
        $this->selectedPackagePreviewId = $id;
    }

    public function applySelectedPackage(): void
    {
        if (!$this->selectedPackagePreviewId) return;
        $this->applyPackage($this->selectedPackagePreviewId);
        $this->closePackagePicker();
    }

    public function mount(int $eventId, ?int $initialItemId = null, ?int $initialDayId = null, ?string $initialView = null): void
    {
        $this->eventId = $eventId;
        $this->activeQuoteId = Quote::where('event_id', $eventId)
            ->where('is_current', true)
            ->latest('version')
            ->value('id');

        // Drilldown aus Sidebar
        if ($initialItemId) {
            $this->openPositions($initialItemId);
        } elseif ($initialDayId) {
            $this->openDay($initialDayId);
        } elseif ($initialView === 'articles') {
            $this->openArticles();
        } else {
            $this->view = 'overview';
        }
    }

    public function openDay(int $dayId): void
    {
        $this->activeDayId = $dayId;
        $this->activeItemId = null;
        $this->view = 'day';
    }

    public function openArticles(): void
    {
        $this->activeDayId = null;
        $this->activeItemId = null;
        $this->view = 'articles';
    }

    public function backToOverview(): void
    {
        $this->activeDayId = null;
        $this->activeItemId = null;
        $this->view = 'overview';
    }

    protected function event(): Event
    {
        $event = Event::findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) {
            abort(403);
        }
        return $event;
    }

    // ========== Quote ==========

    public function createQuote(): void
    {
        $event = $this->event();
        $quote = Quote::create([
            'team_id'    => $event->team_id,
            'user_id'    => Auth::id(),
            'event_id'   => $event->id,
            'token'      => Str::random(48),
            'status'     => 'draft',
            'version'    => 1,
            'is_current' => true,
        ]);
        $this->activeQuoteId = $quote->id;
        ActivityLogger::log($event, 'quote', "Angebot #{$quote->id} (v1) angelegt");
    }

    public function selectQuote(int $quoteId): void
    {
        $q = Quote::where('event_id', $this->eventId)->find($quoteId);
        if ($q) {
            $this->activeQuoteId = $q->id;
        }
    }

    public function newVersion(): void
    {
        if (!$this->activeQuoteId) {
            return;
        }
        $event = $this->event();
        $current = Quote::find($this->activeQuoteId);
        if (!$current || $current->event_id !== $event->id) {
            return;
        }

        $rootId = $current->getRootParentId();
        $maxVersion = (int) Quote::where('event_id', $event->id)
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('parent_id', $rootId);
            })
            ->max('version');

        Quote::where('event_id', $event->id)
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('parent_id', $rootId);
            })
            ->update(['is_current' => false]);

        $newQ = Quote::create([
            'team_id'    => $event->team_id,
            'user_id'    => Auth::id(),
            'event_id'   => $event->id,
            'token'      => Str::random(48),
            'status'     => 'draft',
            'version'    => $maxVersion + 1,
            'parent_id'  => $rootId,
            'is_current' => true,
        ]);

        $this->activeQuoteId = $newQ->id;
        ActivityLogger::log($event, 'quote', "Angebot v{$newQ->version} angelegt");
    }

    // ========== Approval ==========

    public function openApprovalRequest(int $quoteId): void
    {
        $q = Quote::where('event_id', $this->eventId)->find($quoteId);
        if (!$q) return;
        $this->approvalQuoteId = $quoteId;
        $this->approvalApproverId = null;
        $this->approvalComment = '';
        $this->showApprovalModal = true;
    }

    public function closeApprovalModal(): void
    {
        $this->showApprovalModal = false;
        $this->approvalQuoteId = null;
        $this->approvalApproverId = null;
        $this->approvalComment = '';
    }

    public function requestApproval(): void
    {
        if (!$this->approvalQuoteId || !$this->approvalApproverId) return;
        $q = Quote::where('event_id', $this->eventId)->find($this->approvalQuoteId);
        if (!$q) return;

        $q->update([
            'approval_status'       => 'pending',
            'approver_id'           => $this->approvalApproverId,
            'approval_requested_by' => Auth::id(),
            'approval_requested_at' => now(),
            'approval_decided_at'   => null,
            'approval_comment'      => trim($this->approvalComment) ?: null,
        ]);

        $approverName = \Platform\Core\Models\User::find($this->approvalApproverId)?->name ?? 'Unbekannt';
        ActivityLogger::log($this->event(), 'quote', "Angebot v{$q->version}: Freigabe angefordert bei {$approverName}");

        $this->closeApprovalModal();
    }

    public function cancelApprovalRequest(int $quoteId): void
    {
        $q = Quote::where('event_id', $this->eventId)->find($quoteId);
        if (!$q) return;
        $q->update([
            'approval_status'       => 'none',
            'approver_id'           => null,
            'approval_requested_by' => null,
            'approval_requested_at' => null,
            'approval_decided_at'   => null,
            'approval_comment'      => null,
        ]);
        ActivityLogger::log($this->event(), 'quote', "Angebot v{$q->version}: Freigabe-Anfrage zurueckgezogen");
    }

    public function approveQuote(int $quoteId): void
    {
        $q = Quote::where('event_id', $this->eventId)->find($quoteId);
        if (!$q || (int) $q->approver_id !== (int) Auth::id()) return;
        $q->update([
            'approval_status'     => 'approved',
            'approval_decided_at' => now(),
        ]);
        ActivityLogger::log($this->event(), 'quote', "Angebot v{$q->version}: Freigegeben");
    }

    public function rejectQuote(int $quoteId): void
    {
        $q = Quote::where('event_id', $this->eventId)->find($quoteId);
        if (!$q || (int) $q->approver_id !== (int) Auth::id()) return;
        $q->update([
            'approval_status'     => 'rejected',
            'approval_decided_at' => now(),
        ]);
        ActivityLogger::log($this->event(), 'quote', "Angebot v{$q->version}: Freigabe abgelehnt");
    }

    public function setQuoteStatus(string $status): void
    {
        if (!$this->activeQuoteId) return;
        $q = Quote::find($this->activeQuoteId);
        if ($q && $q->event_id === $this->eventId) {
            $old = $q->status;
            $q->update(['status' => $status, 'sent_at' => $status === 'sent' ? now() : $q->sent_at]);
            ActivityLogger::log($this->event(), 'quote', "Angebot-Status: „{$old}“ → „{$status}“");
        }
    }

    public function deleteQuote(int $quoteId): void
    {
        $q = Quote::where('event_id', $this->eventId)->find($quoteId);
        if ($q) {
            $q->delete();
            if ($this->activeQuoteId === $quoteId) {
                $this->activeQuoteId = Quote::where('event_id', $this->eventId)
                    ->where('is_current', true)->latest('version')->value('id');
            }
        }
    }

    /**
     * Override fuer "Raumgrundrisse anhaengen" am Angebot setzen.
     * Werte: 'default' = Team-Default uebernehmen (null),
     *        'on' = explizit anhaengen, 'off' = explizit weglassen.
     */
    public function setQuoteFloorPlans(int $quoteId, string $value): void
    {
        $q = Quote::where('event_id', $this->eventId)->find($quoteId);
        if (!$q) return;

        $new = match ($value) {
            'on'  => true,
            'off' => false,
            default => null,
        };

        $q->update(['attach_floor_plans' => $new]);

        $label = match ($new) {
            true  => 'Grundrisse am Angebot v' . $q->version . ' aktiviert',
            false => 'Grundrisse am Angebot v' . $q->version . ' deaktiviert',
            null  => 'Grundrisse am Angebot v' . $q->version . ' auf Team-Default gesetzt',
        };
        ActivityLogger::log($this->event(), 'quote', $label);
    }

    // ========== QuoteItem ==========

    public function openItemCreate(int $eventDayId): void
    {
        $this->itemEventDayId = $eventDayId;
        $this->itemTyp = 'Speisen';
        $this->itemStatus = 'Entwurf';
        $this->itemBeverageMode = '';
        $this->showItemModal = true;
    }

    public function closeItemModal(): void
    {
        $this->showItemModal = false;
    }

    public function saveItem(): void
    {
        if (!$this->itemEventDayId) return;
        $event = $this->event();

        $maxSort = (int) QuoteItem::where('event_day_id', $this->itemEventDayId)->max('sort_order');

        QuoteItem::create([
            'team_id'       => $event->team_id,
            'user_id'       => Auth::id(),
            'event_day_id'  => $this->itemEventDayId,
            'typ'           => $this->itemTyp,
            'status'        => $this->itemStatus,
            'beverage_mode' => $this->itemBeverageMode !== '' ? $this->itemBeverageMode : null,
            'sort_order'    => $maxSort + 1,
        ]);

        $this->showItemModal = false;
    }

    /**
     * Setzt den Getraenke-Modus am Vorgang. Leerer String/'__none__' = kein Modus.
     */
    public function setItemBeverageMode(int $itemId, string $mode): void
    {
        $event = $this->event();
        $item = QuoteItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $event->id))->find($itemId);
        if (!$item) return;

        $value = ($mode === '' || $mode === '__none__') ? null : $mode;
        $item->update(['beverage_mode' => $value]);
        ActivityLogger::log($event, 'quote', 'Getraenke-Modus am Vorgang "' . $item->typ . '" → ' . ($value ?? 'kein Modus'));
    }

    /**
     * Setzt einen Position-Override fuer den Getraenke-Modus.
     * '__inherit__' / leerer String → null (erbt vom Vorgang),
     * sonst expliziter Override.
     */
    public function setPositionBeverageMode(int $positionId, string $mode): void
    {
        $event = $this->event();
        $pos = QuotePosition::whereHas('quoteItem.eventDay', fn ($q) => $q->where('event_id', $event->id))->find($positionId);
        if (!$pos) return;

        $value = ($mode === '' || $mode === '__inherit__') ? null : $mode;
        $pos->update(['beverage_mode' => $value]);
    }

    public function deleteItem(int $itemId): void
    {
        $event = $this->event();
        $item = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($itemId);
        if ($item) {
            $item->delete();
        }
    }

    /**
     * Status des aktiven Vorgangs wechseln (aus Editor-Header).
     */
    public function updateItemStatus(string $status): void
    {
        if (!$this->activeItemId) return;
        $event = $this->event();
        $item = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($this->activeItemId);
        if ($item) {
            $item->update(['status' => $status]);
        }
    }

    /**
     * Preis-Modus (netto|brutto) fuer das komplette Angebot (event-weit) wechseln.
     * Alle Positionen aller Vorgaenge werden umgerechnet:
     *   netto -> brutto: preis *= (1 + mwst/100)
     *   brutto -> netto: preis /= (1 + mwst/100)
     * gesamt wird mit demselben Faktor skaliert. Bausteine (Headline/Trenntext/
     * Speisentexte) bleiben unberuehrt.
     */
    public function updateQuotePriceMode(string $mode): void
    {
        if (!in_array($mode, ['netto', 'brutto'], true)) return;

        $event = $this->event();
        $old = (string) ($event->quote_price_mode ?? 'netto');
        if ($old === $mode) return;

        $bausteine = ['Headline', 'Trenntext', 'Speisentexte'];
        $itemIds = QuoteItem::whereIn('event_day_id', $event->days->pluck('id'))->pluck('id');
        if ($itemIds->isNotEmpty()) {
            $positions = QuotePosition::whereIn('quote_item_id', $itemIds)->get();
            foreach ($positions as $pos) {
                if (in_array((string) $pos->gruppe, $bausteine, true)) continue;

                $pct = (float) filter_var((string) ($pos->mwst ?? '0%'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if ($pct <= 0) continue;

                $factor = 1 + $pct / 100;
                if ($mode === 'brutto') {
                    $pos->preis  = round((float) $pos->preis  * $factor, 2);
                    $pos->gesamt = round((float) $pos->gesamt * $factor, 2);
                } else {
                    $pos->preis  = round((float) $pos->preis  / $factor, 2);
                    $pos->gesamt = round((float) $pos->gesamt / $factor, 2);
                }
                $pos->save();
            }
        }

        $event->update(['quote_price_mode' => $mode]);
    }

    /**
     * Mwst-Satz des aktiven Vorgangs wechseln.
     */
    public function updateItemMwst(string $mwst): void
    {
        if (!$this->activeItemId) return;
        $event = $this->event();
        $item = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($this->activeItemId);
        if ($item) {
            $item->update(['mwst' => $mwst]);
        }
    }

    // ========== QuotePosition ==========

    public function openPositions(int $itemId): void
    {
        $event = $this->event();
        $item = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($itemId);
        if (!$item) return;
        $this->activeItemId = $itemId;
        $this->activeDayId = $item->event_day_id;
        $this->view = 'editor';
        $this->resetNewPosition();
        $this->dispatch('scroll-to-positions');
    }

    public function closePositions(): void
    {
        // Nach Schliessen des Editors zurueck auf die Tages-Uebersicht, wenn ein Tag bekannt ist,
        // sonst auf die Gesamt-Uebersicht.
        if ($this->activeDayId) {
            $this->view = 'day';
            $this->activeItemId = null;
        } else {
            $this->backToOverview();
        }
    }

    protected function resetNewPosition(): void
    {
        $this->newPosition = [
            'gruppe' => '', 'name' => '', 'anz' => '', 'anz2' => '',
            'uhrzeit' => '', 'bis' => '', 'inhalt' => '', 'gebinde' => '',
            'basis_ek' => 0, 'ek' => 0, 'preis' => 0, 'mwst' => '7%',
            'gesamt' => 0, 'bemerkung' => '',
        ];
    }

    /**
     * Automatische Positions-Berechnungen bei Feld-Aenderungen.
     * Die Logik steckt im PositionCalculator-Service (gemeinsam mit Orders).
     */
    public function updatedNewPosition($value, $key): void
    {
        $this->newPosition = PositionCalculator::apply($this->newPosition, (string) $key, 'preis');
    }

    /**
     * Fuellt die neue Position mit den Daten des gewaehlten Artikels.
     */
    /**
     * Fuegt alle Artikel eines ArticlePackage als QuotePositions an den aktiven
     * Vorgang an. Delegiert an ArticlePackageApplicator.
     */
    public function applyPackage(int $packageId): void
    {
        if (!$this->activeItemId) return;
        $event = $this->event();
        $item = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($this->activeItemId);
        if (!$item) return;

        $package = ArticlePackage::where('team_id', $event->team_id)->find($packageId);
        if (!$package) return;

        try {
            $created = ArticlePackageApplicator::apply($package, $item);
        } catch (\RuntimeException $e) {
            session()->flash('positionError', $e->getMessage());
            return;
        }
        if ($created->isNotEmpty()) {
            $this->recalculateItem($item);
            ActivityLogger::log($event, 'quote', 'Vorlage "' . $package->name . '" eingefuegt (' . $created->count() . ' Positionen)');
        }
    }

    // ========== Pauschal-Regeln anwenden ==========

    public function openFlatRatePicker(int $itemId): void
    {
        $this->flatRateTargetItemId = $itemId;
        $this->showFlatRateModal = true;
    }

    public function closeFlatRatePicker(): void
    {
        $this->showFlatRateModal = false;
        $this->flatRateTargetItemId = null;
    }

    public function applyFlatRate(int $ruleId): void
    {
        $event = $this->event();
        $itemId = $this->flatRateTargetItemId ?? $this->activeItemId;
        if (!$itemId) return;

        $item = QuoteItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $event->id))->find($itemId);
        if (!$item) return;

        $rule = FlatRateRule::where('team_id', $event->team_id)->where('is_active', true)->find($ruleId);
        if (!$rule) {
            session()->flash('positionError', 'Pauschal-Regel nicht gefunden oder inaktiv.');
            return;
        }

        try {
            $result = FlatRateApplicator::apply($rule, $item);
        } catch (\RuntimeException $e) {
            session()->flash('positionError', $e->getMessage());
            return;
        }

        ActivityLogger::log(
            $event,
            'quote',
            'Pauschale "' . $rule->name . '" angewendet: ' . number_format($result['value'], 2, ',', '.') . ' € auf Vorgang "' . $item->typ . '"'
        );

        $this->showFlatRateModal = false;
        $this->flatRateTargetItemId = null;
    }

    // ========== Location-Pricing einbuchen ==========

    /**
     * Liefert die verfuegbaren Locations fuer den EventDay des QuoteItems
     * (alle Bookings mit gesetzter location_id).
     *
     * @return \Illuminate\Support\Collection<int, Location>
     */
    public function locationsForItem(QuoteItem $item): \Illuminate\Support\Collection
    {
        $eventDay = $item->eventDay;
        if (!$eventDay) return collect();

        // Bookings haengen am Event (nicht am EventDay). Filter ueber Event + Datum
        // des Tages liefert die am betreffenden Tag gebuchten Raeume.
        $bookingsQuery = \Platform\Events\Models\Booking::where('event_id', $eventDay->event_id)
            ->whereNotNull('location_id');
        if ($eventDay->datum) {
            $bookingsQuery->where('datum', $eventDay->datum);
        }

        $locationIds = $bookingsQuery
            ->pluck('location_id')
            ->unique()
            ->values();

        if ($locationIds->isEmpty()) return collect();

        return Location::whereIn('id', $locationIds)
            ->orderBy('name')
            ->get();
    }

    public function openLocationPricingPicker(int $itemId): void
    {
        $event = $this->event();
        $item = QuoteItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $event->id))->find($itemId);
        if (!$item) {
            session()->flash('positionError', 'Vorgang nicht gefunden.');
            return;
        }

        $locations = $this->locationsForItem($item);
        if ($locations->isEmpty()) {
            session()->flash('positionError', 'Fuer diesen Tag ist keine Buchung mit Location vorhanden. Bitte zuerst eine Raum-Buchung anlegen.');
            return;
        }

        $this->locationPricingTargetItemId = $itemId;
        $this->locationPricingLocationId   = (int) $locations->first()->id;
        $this->locationPricingPricingIds   = [];
        $this->locationPricingAddonQtys    = [];
        $this->locationPricingWarnings     = [];

        $this->prefillLocationPricingSelection();
        $this->showLocationPricingModal = true;
    }

    public function closeLocationPricingPicker(): void
    {
        $this->showLocationPricingModal = false;
        $this->locationPricingTargetItemId = null;
        $this->locationPricingLocationId = null;
        $this->locationPricingPricingIds = [];
        $this->locationPricingAddonQtys = [];
        $this->locationPricingWarnings = [];
    }

    public function selectLocationForPricing(int $locationId): void
    {
        if (!$this->locationPricingTargetItemId) return;

        $this->locationPricingLocationId = $locationId;
        $this->locationPricingPricingIds = [];
        $this->locationPricingAddonQtys  = [];
        $this->prefillLocationPricingSelection();
    }

    public function toggleLocationPricing(int $pricingId): void
    {
        if (in_array($pricingId, $this->locationPricingPricingIds, true)) {
            $this->locationPricingPricingIds = array_values(array_diff($this->locationPricingPricingIds, [$pricingId]));
        } else {
            $this->locationPricingPricingIds[] = $pricingId;
        }
    }

    public function applyLocationPricing(): void
    {
        $event = $this->event();
        $itemId = $this->locationPricingTargetItemId;
        if (!$itemId) return;

        $item = QuoteItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $event->id))->find($itemId);
        if (!$item) {
            $this->closeLocationPricingPicker();
            return;
        }

        $location = Location::where('team_id', $event->team_id)
            ->find($this->locationPricingLocationId);
        if (!$location) {
            session()->flash('positionError', 'Location nicht gefunden oder gehoert nicht zum Team.');
            return;
        }

        $addonSelections = [];
        foreach ($this->locationPricingAddonQtys as $addonId => $qty) {
            if ($qty === null || $qty === '' || (float) $qty <= 0) {
                continue;
            }
            $addonSelections[] = [
                'addon_id' => (int) $addonId,
                'qty'      => (float) $qty,
            ];
        }

        if (empty($this->locationPricingPricingIds) && empty($addonSelections)) {
            session()->flash('positionError', 'Bitte mindestens ein Pricing oder Add-on auswaehlen.');
            return;
        }

        try {
            $result = LocationPricingApplicator::apply($item, $location, [
                'pricing_ids'      => $this->locationPricingPricingIds,
                'addon_selections' => $addonSelections,
            ]);
        } catch (\RuntimeException $e) {
            session()->flash('positionError', $e->getMessage());
            return;
        }

        $count = count($result['positions']);
        ActivityLogger::log(
            $event,
            'quote',
            'Location-Preise eingebucht: ' . $count . ' Position(en) von "' . $location->name . '" auf Vorgang "' . $item->typ . '"'
        );

        if (!empty($result['warnings'])) {
            session()->flash('positionWarning', implode(' ', $result['warnings']));
        }

        $this->closeLocationPricingPicker();
    }

    /**
     * Liefert die Vorauswahl + Warnings fuer die aktuell gewaehlte Location.
     */
    protected function prefillLocationPricingSelection(): void
    {
        $event = $this->event();
        $itemId = $this->locationPricingTargetItemId;
        $locId  = $this->locationPricingLocationId;

        if (!$itemId || !$locId) return;

        $item = QuoteItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $event->id))->find($itemId);
        $location = Location::where('team_id', $event->team_id)->find($locId);
        if (!$item || !$location) return;

        $suggestion = LocationPricingApplicator::suggestSelection($item, $location);
        $this->locationPricingPricingIds = array_values($suggestion['suggested_pricing_ids']);
        $this->locationPricingWarnings   = array_values($suggestion['warnings']);
    }

    public function pickArticle(int $articleId): void
    {
        $event = $this->event();
        $article = app(CatalogArticleResolverInterface::class)->resolve($articleId, $event->team_id);
        if (!$article) return;

        $this->newPosition['name']     = (string) $article['name'];
        $this->newPosition['gruppe']   = (string) ($article['category_name'] ?? $this->newPosition['gruppe'] ?? '');
        $this->newPosition['inhalt']   = (string) ($article['description'] ?? $article['offer_text'] ?? '');
        $this->newPosition['gebinde']  = (string) ($article['gebinde'] ?? '');
        $this->newPosition['ek']       = (float) ($article['ek'] ?? 0);
        $this->newPosition['basis_ek'] = (float) ($article['ek'] ?? 0);
        $this->newPosition['preis']    = (float) ($article['vk'] ?? 0);
        if (!empty($article['mwst'])) $this->newPosition['mwst'] = (string) $article['mwst'];
    }

    public function addPosition(): void
    {
        if (!$this->activeItemId) return;

        $event = $this->event();
        if ($err = PositionValidator::validate($this->newPosition, PositionValidator::allowedGruppen($event->team_id))) {
            session()->flash('positionError', $err);
            return;
        }

        $item = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($this->activeItemId);
        if (!$item) return;

        $maxSort = (int) QuotePosition::where('quote_item_id', $item->id)->max('sort_order');

        QuotePosition::create([
            'team_id'       => $event->team_id,
            'user_id'       => Auth::id(),
            'quote_item_id' => $item->id,
            'gruppe'        => $this->newPosition['gruppe'],
            'name'          => $this->newPosition['name'],
            'anz'           => (string) $this->newPosition['anz'],
            'anz2'          => (string) $this->newPosition['anz2'],
            'uhrzeit'       => $this->newPosition['uhrzeit'],
            'bis'           => $this->newPosition['bis'],
            'inhalt'        => $this->newPosition['inhalt'],
            'gebinde'       => $this->newPosition['gebinde'],
            'basis_ek'      => (float) $this->newPosition['basis_ek'],
            'ek'            => (float) $this->newPosition['ek'],
            'preis'         => (float) $this->newPosition['preis'],
            'mwst'          => $this->newPosition['mwst'],
            'gesamt'        => (float) ($this->newPosition['gesamt'] ?: ((float) $this->newPosition['anz']) * ((float) $this->newPosition['preis'])),
            'bemerkung'     => $this->newPosition['bemerkung'],
            'sort_order'    => $maxSort + 1,
        ]);

        $this->recalculateItem($item);
        $this->resetNewPosition();
    }

    /**
     * Aktualisiert ein einzelnes Feld einer bestehenden QuotePosition und
     * rechnet abhaengige Felder (Gesamt, Anz2 bei Uhrzeit, Preis bei Gesamt) neu.
     */
    public function updatePositionField(int $positionId, string $field, $value): void
    {
        $allowed = ['gruppe','name','anz','anz2','uhrzeit','bis','gebinde','ek','preis','mwst','gesamt','bemerkung','procurement_type'];
        if (!in_array($field, $allowed, true)) return;
        if (!$this->activeItemId) return;

        // Server-seitige Defensive: ungueltige Zeiten nicht speichern
        if (in_array($field, ['uhrzeit','bis'], true)) {
            $v = trim((string) $value);
            if ($v !== '' && !PositionCalculator::isValidTime($v)) {
                session()->flash('positionError', 'Uhrzeit "' . $v . '" ist nicht zulaessig.');
                return;
            }
        }

        $pos = QuotePosition::where('quote_item_id', $this->activeItemId)->find($positionId);
        if (!$pos) return;

        $pos->{$field} = $value;

        // Abhaengige Felder via Service rechnen und ins Model uebertragen
        $data = PositionCalculator::apply([
            'anz'     => $pos->anz,
            'anz2'    => $pos->anz2,
            'uhrzeit' => $pos->uhrzeit,
            'bis'     => $pos->bis,
            'preis'   => $pos->preis,
            'gesamt'  => $pos->gesamt,
        ], $field, 'preis');

        $pos->anz2   = $data['anz2']   ?? $pos->anz2;
        $pos->preis  = $data['preis']  ?? $pos->preis;
        $pos->gesamt = $data['gesamt'] ?? $pos->gesamt;

        $pos->save();

        $item = QuoteItem::find($this->activeItemId);
        if ($item) $this->recalculateItem($item);
    }

    /**
     * Verschiebt eine Position eins nach oben oder unten (swap sort_order).
     */
    public function movePosition(int $positionId, string $direction): void
    {
        if (!$this->activeItemId) return;
        if (!in_array($direction, ['up', 'down'], true)) return;

        $pos = QuotePosition::where('quote_item_id', $this->activeItemId)->find($positionId);
        if (!$pos) return;

        $neighbor = QuotePosition::where('quote_item_id', $this->activeItemId)
            ->where('sort_order', $direction === 'up' ? '<' : '>', $pos->sort_order)
            ->orderBy('sort_order', $direction === 'up' ? 'desc' : 'asc')
            ->first();

        if (!$neighbor) return;

        $tmp = $pos->sort_order;
        $pos->update(['sort_order' => $neighbor->sort_order]);
        $neighbor->update(['sort_order' => $tmp]);
    }

    /**
     * Konvertiert einen QuoteItem in einen OrderItem. Delegiert an
     * QuoteOrderConverter.
     */
    public function convertQuoteItemToOrder(int $quoteItemId): void
    {
        $event = $this->event();
        $quoteItem = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))
            ->find($quoteItemId);
        if (!$quoteItem) return;
        $baseTyp = (string) $quoteItem->typ;
        QuoteOrderConverter::convertItem($quoteItem);
        ActivityLogger::log($event, 'quote', 'Vorgang "' . $baseTyp . '" in Bestellung ueberfuehrt');
    }

    /**
     * Synchronisiert einen bestehenden OrderItem mit dem QuoteItem.
     * Delegiert an QuoteOrderConverter.
     */
    public function syncQuoteItemToOrder(int $quoteItemId): void
    {
        $event = $this->event();
        $quoteItem = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))
            ->find($quoteItemId);
        if (!$quoteItem) return;

        $orderItem = QuoteOrderConverter::syncItem($quoteItem);
        if (!$orderItem) {
            session()->flash('quoteSyncError', 'Kein passender Bestell-Vorgang gefunden. Bitte zuerst "In Bestellung" ausfuehren.');
            return;
        }
        ActivityLogger::log($event, 'quote', 'Bestellung "' . $orderItem->typ . '" mit Angebot synchronisiert');
    }

    /**
     * Konvertiert alle QuoteItems eines Tages in OrderItems.
     */
    public function convertAllQuoteItemsOfDayToOrder(int $dayId): void
    {
        QuoteOrderConverter::convertAllForDay($this->event(), $dayId);
    }

    /**
     * Konvertiert alle QuoteItems des gesamten Events in OrderItems.
     */
    public function convertAllQuoteItemsToOrder(): void
    {
        QuoteOrderConverter::convertAllForEvent($this->event());
    }

    public function deletePosition(int $positionId): void
    {
        if (!$this->activeItemId) return;
        $pos = QuotePosition::where('quote_item_id', $this->activeItemId)->find($positionId);
        if ($pos) {
            $uuid = (string) $pos->uuid;
            $pos->delete();
            $this->selectedPositionUuids = MultiSelectHelper::remove($this->selectedPositionUuids, [$uuid]);
            $item = QuoteItem::find($this->activeItemId);
            if ($item) $this->recalculateItem($item);
        }
    }

    // ---------- Mehrfach-Auswahl ----------

    public function toggleAllPositions(): void
    {
        if (!$this->activeItemId) return;
        $all = QuotePosition::where('quote_item_id', $this->activeItemId)->pluck('uuid')
            ->map(fn ($u) => (string) $u)->all();
        $this->selectedPositionUuids = MultiSelectHelper::toggleAll($this->selectedPositionUuids, $all);
    }

    public function togglePositionSelection(string $uuid): void
    {
        $this->selectedPositionUuids = MultiSelectHelper::toggleSingle($this->selectedPositionUuids, $uuid);
    }

    public function togglePositionRange(int $from, int $to, bool $select): void
    {
        if (!$this->activeItemId) return;
        $uuids = QuotePosition::where('quote_item_id', $this->activeItemId)
            ->orderBy('sort_order')->pluck('uuid')->map(fn ($u) => (string) $u)->toArray();
        $this->selectedPositionUuids = MultiSelectHelper::toggleRange($this->selectedPositionUuids, $uuids, $from, $to, $select);
    }

    public function clearPositionSelection(): void
    {
        $this->selectedPositionUuids = [];
    }

    /**
     * Neue Sortierung der Positionen aus Drag-&-Drop uebernehmen.
     */
    public function reorderQuotePositions(array $uuids): void
    {
        if (!$this->activeItemId) return;
        foreach ($uuids as $index => $uuid) {
            QuotePosition::where('quote_item_id', $this->activeItemId)
                ->where('uuid', $uuid)
                ->update(['sort_order' => $index]);
        }
    }

    public function deleteSelectedPositions(): void
    {
        if (!$this->activeItemId || empty($this->selectedPositionUuids)) return;
        $count = count($this->selectedPositionUuids);
        QuotePosition::where('quote_item_id', $this->activeItemId)
            ->whereIn('uuid', $this->selectedPositionUuids)->delete();
        $this->selectedPositionUuids = [];
        $item = QuoteItem::find($this->activeItemId);
        if ($item) {
            $this->recalculateItem($item);
            ActivityLogger::log($this->event(), 'quote', "{$count} Position(en) geloescht");
        }
    }

    protected function recalculateItem(QuoteItem $item): void
    {
        $positions = $item->posList()->get();

        $bausteinNames = collect(SettingsService::bausteine($item->team_id))
            ->map(fn ($b) => mb_strtolower(trim((string) ($b['name'] ?? ''))))
            ->filter()
            ->all();
        $isBaustein = fn ($gruppe) => in_array(mb_strtolower(trim((string) $gruppe)), $bausteinNames, true);

        $item->update([
            // artikel = Positionen ohne Bausteine (Headline/Speisentexte/Trenntext etc.)
            'artikel'    => $positions->filter(fn ($p) => !$isBaustein($p->gruppe))->count(),
            // positionen = alle Zeilen inkl. Bausteine
            'positionen' => $positions->count(),
            'umsatz'     => (float) $positions->sum('gesamt'),
        ]);
    }

    public function render()
    {
        $event = Event::with(['days' => fn($q) => $q->orderBy('sort_order')])->findOrFail($this->eventId);

        $quotes = Quote::where('event_id', $event->id)
            ->orderByDesc('version')
            ->get();

        $activeQuote = $this->activeQuoteId ? Quote::find($this->activeQuoteId) : null;

        $items = QuoteItem::whereIn('event_day_id', $event->days->pluck('id'))
            ->with(['posList:id,quote_item_id,gesamt,mwst,gruppe'])
            ->orderBy('sort_order')->get()->groupBy('event_day_id');

        $activeItem = null;
        $positions = collect();
        if ($this->activeItemId) {
            $activeItem = QuoteItem::find($this->activeItemId);
            if ($activeItem) {
                $positions = $activeItem->posList()->orderBy('sort_order')->get();
            }
        }

        $activeDay = $this->activeDayId ? $event->days->firstWhere('id', $this->activeDayId) : null;

        // Fuer "Alle Artikel"-Ansicht: alle Positionen ueber alle Tage flach, mit Tag- und Vorgangs-Info
        $allPositions = collect();
        if ($this->view === 'articles') {
            $itemIds = $items->flatten()->pluck('id');
            $allPositions = QuotePosition::whereIn('quote_item_id', $itemIds)
                ->orderBy('quote_item_id')
                ->orderBy('sort_order')
                ->get();
        }

        $bausteine = SettingsService::bausteine($event->team_id);
        $beverageModes = SettingsService::beverageModes($event->team_id);

        // Artikel-Vorlagen (Packages) fuer den "Vorlage einfuegen"-Modal
        $packagesQuery = ArticlePackage::where('team_id', $event->team_id)
            ->where('is_active', true);
        if ($this->showPackagePicker && trim($this->packageSearch) !== '') {
            $like = '%' . str_replace(['%','_'], ['\\%','\\_'], trim($this->packageSearch)) . '%';
            $packagesQuery->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('description', 'like', $like);
            });
        }
        $articlePackages = $packagesQuery
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedPackagePreview = null;
        if ($this->showPackagePicker && $this->selectedPackagePreviewId) {
            $selectedPackagePreview = ArticlePackage::with(['items' => fn($q) => $q->orderBy('sort_order')])
                ->where('team_id', $event->team_id)
                ->find($this->selectedPackagePreviewId);
        }

        // Team-Mitglieder fuer den Approver-Picker
        $team = \Platform\Core\Models\Team::find($event->team_id);
        $teamUsers = $team
            ? $team->users()->orderBy('name')->get(['users.id', 'users.name', 'users.email'])
                ->reject(fn ($u) => (int) $u->id === (int) Auth::id())
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->values()
                ->all()
            : [];

        // Artikel-Suche via ArticleSearchService (nur im Editor-Modus)
        $articleMatches = $this->view === 'editor'
            ? ArticleSearchService::search($event->team_id, (string) ($this->newPosition['name'] ?? ''))
            : collect();

        $eventWidePositionCount = (int) QuotePosition::whereIn('quote_item_id',
            QuoteItem::whereIn('event_day_id', $event->days->pluck('id'))->pluck('id')
        )->count();

        $allowedGruppen = PositionValidator::allowedGruppen($event->team_id);

        // Pauschal-Regeln, die zum aktiven Vorgang passen, + aktuelle Applications
        $eligibleFlatRates = collect();
        $activeFlatRateApplications = collect();
        if ($activeItem) {
            $eligibleFlatRates = FlatRateRule::where('team_id', $event->team_id)
                ->where('is_active', true)
                ->orderBy('priority')
                ->orderBy('name')
                ->get()
                ->filter(fn (FlatRateRule $r) => $r->matches((string) $activeItem->typ, (string) $event->event_type))
                ->values();

            $activeFlatRateApplications = FlatRateApplication::where('quote_item_id', $activeItem->id)
                ->whereNull('superseded_at')
                ->with(['rule:id,name', 'quotePosition'])
                ->get()
                ->keyBy('rule_id');
        }

        // Raum-Grundrisse: wie viele gebuchte Raeume haben einen Grundriss?
        $bookedLocations = $event->bookings()
            ->whereNotNull('location_id')
            ->with('location')
            ->get()
            ->pluck('location')
            ->filter()
            ->unique(fn ($loc) => $loc->id)
            ->values();
        $floorPlanRoomCount   = $bookedLocations->count();
        $floorPlanReadyCount  = $bookedLocations->filter(fn ($loc) => $loc->hasFloorPlan())->count();
        $floorPlansTeamDefault = SettingsService::attachFloorPlansDefault($event->team_id);

        return view('events::livewire.detail.quotes', [
            'event'          => $event,
            'quotes'         => $quotes,
            'activeQuote'    => $activeQuote,
            'days'           => $event->days,
            'items'          => $items,
            'activeItem'     => $activeItem,
            'eventWidePositionCount' => $eventWidePositionCount,
            'allowedGruppen' => $allowedGruppen,
            'activeDay'      => $activeDay,
            'allPositions'   => $allPositions,
            'positions'      => $positions,
            'bausteine'      => $bausteine,
            'beverageModes'  => $beverageModes,
            'articleMatches' => $articleMatches,
            'articlePackages'=> $articlePackages,
            'selectedPackagePreview' => $selectedPackagePreview,
            'teamUsers'      => $teamUsers,
            'currentUserId'  => Auth::id(),
            'eligibleFlatRates' => $eligibleFlatRates,
            'activeFlatRateApplications' => $activeFlatRateApplications,
            'floorPlanRoomCount'     => $floorPlanRoomCount,
            'floorPlanReadyCount'    => $floorPlanReadyCount,
            'floorPlansTeamDefault'  => $floorPlansTeamDefault,
        ]);
    }
}
