<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Events\Models\Article;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Models\Quote;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Services\ArticleSearchService;
use Platform\Events\Services\PositionCalculator;
use Platform\Events\Services\SettingsService;

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

    public bool $showItemModal = false;
    public ?int $itemEventDayId = null;
    public string $itemTyp = 'Speisen';
    public string $itemStatus = 'Entwurf';
    public string $itemMwst = '19%';

    // Approval-Modal
    public bool $showApprovalModal = false;
    public ?int $approvalQuoteId = null;
    public ?int $approvalApproverId = null;
    public string $approvalComment = '';

    // Package/Vorlage-Picker
    public bool $showPackagePicker = false;
    public string $packageSearch = '';
    public ?int $selectedPackagePreviewId = null;

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

    // ========== QuoteItem ==========

    public function openItemCreate(int $eventDayId): void
    {
        $this->itemEventDayId = $eventDayId;
        $this->itemTyp = 'Speisen';
        $this->itemStatus = 'Entwurf';
        $this->itemMwst = '19%';
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
            'team_id'      => $event->team_id,
            'user_id'      => Auth::id(),
            'event_day_id' => $this->itemEventDayId,
            'typ'          => $this->itemTyp,
            'status'       => $this->itemStatus,
            'mwst'         => $this->itemMwst,
            'sort_order'   => $maxSort + 1,
        ]);

        $this->showItemModal = false;
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
     * Vorgang an.
     */
    public function applyPackage(int $packageId): void
    {
        if (!$this->activeItemId) return;
        $event = $this->event();
        $item = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($this->activeItemId);
        if (!$item) return;

        $package = ArticlePackage::with(['items' => fn($q) => $q->orderBy('sort_order')])
            ->where('team_id', $event->team_id)
            ->find($packageId);
        if (!$package) return;

        $maxSort = (int) QuotePosition::where('quote_item_id', $item->id)->max('sort_order');
        $created = 0;

        foreach ($package->items as $pi) {
            $article = $pi->article_id
                ? Article::with('group:id,name')->where('team_id', $event->team_id)->find($pi->article_id)
                : null;

            $name    = (string) ($pi->name ?? $article?->name ?? '');
            $gruppe  = (string) ($pi->gruppe ?? $article?->group?->name ?? '');
            $gebinde = (string) ($pi->gebinde ?? $article?->gebinde ?? '');
            $anz     = (string) ($pi->quantity ?? 1);
            $ek      = (float)  ($article->ek ?? 0);
            $preis   = (float)  ($pi->vk ?? $article?->vk ?? 0);
            $mwst    = (string) ($article?->mwst ?? '7%');
            $gesamt  = (float)  ($pi->gesamt ?: ((float) $anz) * $preis);

            QuotePosition::create([
                'team_id'       => $event->team_id,
                'user_id'       => Auth::id(),
                'quote_item_id' => $item->id,
                'gruppe'        => $gruppe,
                'name'          => $name,
                'anz'           => $anz,
                'gebinde'       => $gebinde,
                'basis_ek'      => $ek,
                'ek'            => $ek,
                'preis'         => $preis,
                'mwst'          => $mwst,
                'gesamt'        => $gesamt,
                'sort_order'    => ++$maxSort,
            ]);
            $created++;
        }

        if ($created > 0) {
            $this->recalculateItem($item);
            ActivityLogger::log($event, 'quote', 'Vorlage "' . $package->name . '" eingefuegt (' . $created . ' Positionen)');
        }
    }

    public function pickArticle(int $articleId): void
    {
        $event = $this->event();
        $article = Article::with('group:id,name')->where('team_id', $event->team_id)->find($articleId);
        if (!$article) return;

        $this->newPosition['name']     = (string) $article->name;
        $this->newPosition['gruppe']   = (string) ($article->group?->name ?? $this->newPosition['gruppe'] ?? '');
        $this->newPosition['inhalt']   = (string) ($article->description ?? $article->offer_text ?? '');
        $this->newPosition['gebinde']  = (string) ($article->gebinde ?? '');
        $this->newPosition['ek']       = (float) ($article->ek ?? 0);
        $this->newPosition['basis_ek'] = (float) ($article->ek ?? 0);
        $this->newPosition['preis']    = (float) ($article->vk ?? 0);
        if (!empty($article->mwst)) $this->newPosition['mwst'] = (string) $article->mwst;
    }

    public function addPosition(): void
    {
        if (!$this->activeItemId) return;
        $event = $this->event();
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
        $allowed = ['gruppe','name','anz','anz2','uhrzeit','bis','gebinde','ek','preis','mwst','gesamt','bemerkung'];
        if (!in_array($field, $allowed, true)) return;
        if (!$this->activeItemId) return;

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
     * Konvertiert einen QuoteItem (Angebots-Vorgang) in einen OrderItem
     * (Bestell-Vorgang) inkl. aller Positionen. Bei Typ-Konflikt am selben Tag
     * wird ein Suffix "(2)", "(3)" angehaengt.
     */
    public function convertQuoteItemToOrder(int $quoteItemId): void
    {
        $event = $this->event();
        $quoteItem = QuoteItem::with('posList')
            ->whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))
            ->find($quoteItemId);
        if (!$quoteItem) return;

        $dayId = $quoteItem->event_day_id;

        // Dedupe typ: "Speisen" -> "Speisen (2)" falls schon existiert
        $baseTyp = (string) $quoteItem->typ;
        $existingTyps = OrderItem::where('event_day_id', $dayId)->pluck('typ')->toArray();
        $typ = $baseTyp;
        $counter = 2;
        while (in_array($typ, $existingTyps, true)) {
            $typ = $baseTyp . ' (' . $counter . ')';
            $counter++;
        }

        $maxSort = (int) OrderItem::where('event_day_id', $dayId)->max('sort_order');

        $orderItem = OrderItem::create([
            'team_id'      => $event->team_id,
            'user_id'      => Auth::id(),
            'event_day_id' => $dayId,
            'typ'          => $typ,
            'status'       => 'Offen',
            'lieferant'    => '',
            'artikel'      => (int) $quoteItem->artikel,
            'positionen'   => (int) $quoteItem->positionen,
            'einkauf'      => 0,
            'sort_order'   => $maxSort + 1,
        ]);

        foreach ($quoteItem->posList as $pos) {
            OrderPosition::create([
                'team_id'       => $event->team_id,
                'user_id'       => Auth::id(),
                'order_item_id' => $orderItem->id,
                'gruppe'        => $pos->gruppe,
                'name'          => $pos->name,
                'anz'           => $pos->anz,
                'anz2'          => $pos->anz2,
                'uhrzeit'       => $pos->uhrzeit,
                'bis'           => $pos->bis,
                'inhalt'        => $pos->inhalt,
                'gebinde'       => $pos->gebinde,
                'basis_ek'      => $pos->basis_ek,
                'ek'            => $pos->ek,
                'preis'         => $pos->preis,
                'mwst'          => $pos->mwst,
                'gesamt'        => $pos->gesamt,
                'bemerkung'     => $pos->bemerkung,
                'sort_order'    => $pos->sort_order,
            ]);
        }

        $this->syncOrderItemSummary($orderItem);
        ActivityLogger::log($event, 'quote', 'Vorgang "' . $baseTyp . '" in Bestellung ueberfuehrt');
    }

    /**
     * Synchronisiert einen bestehenden OrderItem mit den aktuellen Positionen
     * des QuoteItem. Matching via exakter Typ oder Basis-Typ + "(N)"-Suffix.
     * Bestehende OrderPositions werden geloescht und neu angelegt.
     */
    public function syncQuoteItemToOrder(int $quoteItemId): void
    {
        $event = $this->event();
        $quoteItem = QuoteItem::with('posList')
            ->whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))
            ->find($quoteItemId);
        if (!$quoteItem) return;

        $dayId = $quoteItem->event_day_id;
        $baseTyp = preg_replace('/\s*\(\d+\)$/', '', (string) $quoteItem->typ);

        $orderItem = OrderItem::where('event_day_id', $dayId)
            ->where(function ($q) use ($quoteItem, $baseTyp) {
                $q->where('typ', $quoteItem->typ)
                  ->orWhere('typ', $baseTyp)
                  ->orWhere('typ', 'like', $baseTyp . ' (%');
            })
            ->first();

        if (!$orderItem) {
            session()->flash('quoteSyncError', 'Kein passender Bestell-Vorgang gefunden. Bitte zuerst "In Bestellung" ausfuehren.');
            return;
        }

        $orderItem->posList()->delete();

        foreach ($quoteItem->posList as $pos) {
            OrderPosition::create([
                'team_id'       => $event->team_id,
                'user_id'       => Auth::id(),
                'order_item_id' => $orderItem->id,
                'gruppe'        => $pos->gruppe,
                'name'          => $pos->name,
                'anz'           => $pos->anz,
                'anz2'          => $pos->anz2,
                'uhrzeit'       => $pos->uhrzeit,
                'bis'           => $pos->bis,
                'inhalt'        => $pos->inhalt,
                'gebinde'       => $pos->gebinde,
                'basis_ek'      => $pos->basis_ek,
                'ek'            => $pos->ek,
                'preis'         => $pos->preis,
                'mwst'          => $pos->mwst,
                'gesamt'        => $pos->gesamt,
                'bemerkung'     => $pos->bemerkung,
                'sort_order'    => $pos->sort_order,
            ]);
        }

        $this->syncOrderItemSummary($orderItem);
        ActivityLogger::log($event, 'quote', 'Bestellung "' . $orderItem->typ . '" mit Angebot synchronisiert');
    }

    protected function syncOrderItemSummary(OrderItem $item): void
    {
        $positions = $item->posList()->get();
        $item->update([
            'positionen' => $positions->where('gesamt', '>', 0)->count(),
            'einkauf'    => (float) $positions->sum('ek'),
        ]);
    }

    /**
     * Konvertiert alle QuoteItems eines Tages in OrderItems.
     */
    public function convertAllQuoteItemsOfDayToOrder(int $dayId): void
    {
        $event = $this->event();
        $itemIds = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))
            ->where('event_day_id', $dayId)
            ->orderBy('sort_order')
            ->pluck('id');
        foreach ($itemIds as $id) {
            $this->convertQuoteItemToOrder((int) $id);
        }
    }

    /**
     * Konvertiert alle QuoteItems des gesamten Events in OrderItems.
     */
    public function convertAllQuoteItemsToOrder(): void
    {
        $event = $this->event();
        $itemIds = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))
            ->orderBy('sort_order')
            ->pluck('id');
        foreach ($itemIds as $id) {
            $this->convertQuoteItemToOrder((int) $id);
        }
    }

    public function deletePosition(int $positionId): void
    {
        if (!$this->activeItemId) return;
        $pos = QuotePosition::where('quote_item_id', $this->activeItemId)->find($positionId);
        if ($pos) {
            $pos->delete();
            $item = QuoteItem::find($this->activeItemId);
            if ($item) $this->recalculateItem($item);
        }
    }

    protected function recalculateItem(QuoteItem $item): void
    {
        $positions = $item->posList()->get();
        $item->update([
            'artikel'    => $positions->count(),
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

        return view('events::livewire.detail.quotes', [
            'event'          => $event,
            'quotes'         => $quotes,
            'activeQuote'    => $activeQuote,
            'days'           => $event->days,
            'items'          => $items,
            'activeItem'     => $activeItem,
            'activeDay'      => $activeDay,
            'allPositions'   => $allPositions,
            'positions'      => $positions,
            'bausteine'      => $bausteine,
            'articleMatches' => $articleMatches,
            'articlePackages'=> $articlePackages,
            'selectedPackagePreview' => $selectedPackagePreview,
            'teamUsers'      => $teamUsers,
            'currentUserId'  => Auth::id(),
        ]);
    }
}
