<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Article;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Services\ArticleSearchService;
use Platform\Events\Services\MultiSelectHelper;
use Platform\Events\Services\PositionCalculator;
use Platform\Events\Services\PositionValidator;
use Platform\Events\Services\SettingsService;

class Orders extends Component
{
    public int $eventId;
    public ?int $activeItemId = null;
    public ?int $activeDayId = null;
    public string $view = 'overview';

    public bool $showItemModal = false;
    public ?int $itemEventDayId = null;
    public string $itemTyp = 'Speisen';
    public string $itemStatus = 'Offen';
    public string $itemLieferant = '';
    public string $itemPriceMode = 'netto';

    public array $newPosition = [
        'gruppe' => '', 'name' => '', 'anz' => '', 'anz2' => '',
        'uhrzeit' => '', 'bis' => '', 'inhalt' => '', 'gebinde' => '',
        'basis_ek' => 0, 'ek' => 0, 'preis' => 0, 'mwst' => '7%',
        'gesamt' => 0, 'bemerkung' => '',
    ];

    // Mehrfachauswahl fuer Bulk-Delete von Positionen
    public array $selectedPositionUuids = [];

    public function mount(int $eventId, ?int $initialItemId = null, ?int $initialDayId = null, ?string $initialView = null): void
    {
        $this->eventId = $eventId;

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

    public function openItemCreate(int $eventDayId): void
    {
        $this->itemEventDayId = $eventDayId;
        $this->itemTyp = 'Speisen';
        $this->itemStatus = 'Offen';
        $this->itemLieferant = '';
        $this->itemPriceMode = 'netto';
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
        $maxSort = (int) OrderItem::where('event_day_id', $this->itemEventDayId)->max('sort_order');
        $item = OrderItem::create([
            'team_id'      => $event->team_id,
            'user_id'      => Auth::id(),
            'event_day_id' => $this->itemEventDayId,
            'typ'          => $this->itemTyp,
            'status'       => $this->itemStatus,
            'price_mode'   => $this->itemPriceMode,
            'lieferant'    => $this->itemLieferant,
            'sort_order'   => $maxSort + 1,
        ]);
        ActivityLogger::log($event, 'order', "Bestellung \"{$item->typ}\" angelegt");
        $this->showItemModal = false;
    }

    public function deleteItem(int $itemId): void
    {
        $event = $this->event();
        $item = OrderItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($itemId);
        if ($item) {
            $typ = $item->typ;
            $item->delete();
            ActivityLogger::log($event, 'order', "Bestellung \"{$typ}\" geloescht");
        }
    }

    public function updateItemStatus(string $status): void
    {
        if (!$this->activeItemId) return;
        $event = $this->event();
        $item = OrderItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($this->activeItemId);
        if ($item) {
            $old = (string) $item->status;
            $item->update(['status' => $status]);
            if ($old !== $status) {
                ActivityLogger::log($event, 'order', "Bestellung '{$item->typ}' Status: '{$old}' → '{$status}'");
            }
        }
    }

    public function updateItemPriceMode(string $mode): void
    {
        if (!in_array($mode, ['netto', 'brutto'], true)) return;
        if (!$this->activeItemId) return;

        $event = $this->event();
        $item = OrderItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($this->activeItemId);
        if (!$item) return;

        $old = (string) ($item->price_mode ?? 'netto');
        if ($old === $mode) return;

        $bausteine = ['Headline', 'Trenntext', 'Speisentexte'];
        foreach ($item->posList as $pos) {
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

        $item->update(['price_mode' => $mode]);
    }

    public function updateItemLieferant(string $lieferant): void
    {
        if (!$this->activeItemId) return;
        $event = $this->event();
        $item = OrderItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($this->activeItemId);
        if ($item) $item->update(['lieferant' => $lieferant ?: null]);
    }

    public function convertFromQuote(int $quoteItemId): void
    {
        $event = $this->event();
        $qItem = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->with('posList')->find($quoteItemId);
        if (!$qItem) return;

        $maxSort = (int) OrderItem::where('event_day_id', $qItem->event_day_id)->max('sort_order');

        $oItem = OrderItem::create([
            'team_id'      => $event->team_id,
            'user_id'      => Auth::id(),
            'event_day_id' => $qItem->event_day_id,
            'typ'          => $qItem->typ,
            'status'       => 'Offen',
            'lieferant'    => null,
            'sort_order'   => $maxSort + 1,
        ]);

        foreach ($qItem->posList as $p) {
            OrderPosition::create([
                'team_id'       => $event->team_id,
                'user_id'       => Auth::id(),
                'order_item_id' => $oItem->id,
                'gruppe'        => $p->gruppe,
                'name'          => $p->name,
                'anz'           => $p->anz,
                'anz2'          => $p->anz2,
                'uhrzeit'       => $p->uhrzeit,
                'bis'           => $p->bis,
                'inhalt'        => $p->inhalt,
                'gebinde'       => $p->gebinde,
                'basis_ek'      => $p->basis_ek,
                'ek'            => $p->ek,
                'preis'         => $p->preis,
                'mwst'          => $p->mwst,
                'gesamt'        => $p->gesamt,
                'bemerkung'     => $p->bemerkung,
                'sort_order'    => $p->sort_order,
            ]);
        }

        $this->recalculateItem($oItem);
    }

    public function openPositions(int $itemId): void
    {
        $item = OrderItem::find($itemId);
        $this->activeItemId = $itemId;
        $this->activeDayId = $item?->event_day_id;
        $this->view = 'editor';
        $this->resetNewPosition();
        $this->dispatch('scroll-to-positions');
    }

    public function closePositions(): void
    {
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

    public function updatedNewPosition($value, $key): void
    {
        $this->newPosition = PositionCalculator::apply($this->newPosition, (string) $key, 'ek');
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
        if ($err = PositionValidator::validate($this->newPosition, PositionValidator::allowedGruppen($event->team_id))) {
            session()->flash('positionError', $err);
            return;
        }

        $item = OrderItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($this->activeItemId);
        if (!$item) return;

        $maxSort = (int) OrderPosition::where('order_item_id', $item->id)->max('sort_order');

        OrderPosition::create(array_merge($this->newPosition, [
            'team_id'       => $event->team_id,
            'user_id'       => Auth::id(),
            'order_item_id' => $item->id,
            'anz'           => (string) $this->newPosition['anz'],
            'anz2'          => (string) $this->newPosition['anz2'],
            'basis_ek'      => (float) $this->newPosition['basis_ek'],
            'ek'            => (float) $this->newPosition['ek'],
            'preis'         => (float) $this->newPosition['preis'],
            'gesamt'        => (float) ($this->newPosition['gesamt'] ?: ((float) $this->newPosition['anz']) * ((float) $this->newPosition['ek'])),
            'sort_order'    => $maxSort + 1,
        ]));

        $this->recalculateItem($item);
        $this->resetNewPosition();
    }

    public function deletePosition(int $positionId): void
    {
        if (!$this->activeItemId) return;
        $pos = OrderPosition::where('order_item_id', $this->activeItemId)->find($positionId);
        if ($pos) {
            $uuid = (string) $pos->uuid;
            $pos->delete();
            $this->selectedPositionUuids = MultiSelectHelper::remove($this->selectedPositionUuids, [$uuid]);
            $item = OrderItem::find($this->activeItemId);
            if ($item) $this->recalculateItem($item);
        }
    }

    // ---------- Mehrfach-Auswahl ----------

    public function toggleAllPositions(): void
    {
        if (!$this->activeItemId) return;
        $all = OrderPosition::where('order_item_id', $this->activeItemId)->pluck('uuid')
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
        $uuids = OrderPosition::where('order_item_id', $this->activeItemId)
            ->orderBy('sort_order')->pluck('uuid')->map(fn ($u) => (string) $u)->toArray();
        $this->selectedPositionUuids = MultiSelectHelper::toggleRange($this->selectedPositionUuids, $uuids, $from, $to, $select);
    }

    public function clearPositionSelection(): void
    {
        $this->selectedPositionUuids = [];
    }

    public function reorderOrderPositions(array $uuids): void
    {
        if (!$this->activeItemId) return;
        foreach ($uuids as $index => $uuid) {
            OrderPosition::where('order_item_id', $this->activeItemId)
                ->where('uuid', $uuid)
                ->update(['sort_order' => $index]);
        }
    }

    public function deleteSelectedPositions(): void
    {
        if (!$this->activeItemId || empty($this->selectedPositionUuids)) return;
        $count = count($this->selectedPositionUuids);
        OrderPosition::where('order_item_id', $this->activeItemId)
            ->whereIn('uuid', $this->selectedPositionUuids)->delete();
        $this->selectedPositionUuids = [];
        $item = OrderItem::find($this->activeItemId);
        if ($item) {
            $this->recalculateItem($item);
            ActivityLogger::log($this->event(), 'order', "{$count} Position(en) geloescht");
        }
    }

    protected function recalculateItem(OrderItem $item): void
    {
        $positions = $item->posList()->get();

        $bausteinNames = collect(SettingsService::bausteine($item->team_id))
            ->map(fn ($b) => mb_strtolower(trim((string) ($b['name'] ?? ''))))
            ->filter()
            ->all();
        $isBaustein = fn ($gruppe) => in_array(mb_strtolower(trim((string) $gruppe)), $bausteinNames, true);

        $item->update([
            'artikel'    => $positions->filter(fn ($p) => !$isBaustein($p->gruppe))->count(),
            'positionen' => $positions->count(),
            'einkauf'    => (float) $positions->sum('gesamt'),
        ]);
    }

    public function render()
    {
        $event = Event::with(['days' => fn($q) => $q->orderBy('sort_order')])->findOrFail($this->eventId);

        $items = OrderItem::whereIn('event_day_id', $event->days->pluck('id'))
            ->orderBy('sort_order')->get()->groupBy('event_day_id');

        $quoteItems = QuoteItem::whereIn('event_day_id', $event->days->pluck('id'))
            ->orderBy('sort_order')->get()->groupBy('event_day_id');

        $activeItem = null;
        $positions = collect();
        if ($this->activeItemId) {
            $activeItem = OrderItem::find($this->activeItemId);
            if ($activeItem) {
                $positions = $activeItem->posList()->orderBy('sort_order')->get();
            }
        }

        $activeDay = $this->activeDayId ? $event->days->firstWhere('id', $this->activeDayId) : null;

        $allPositions = collect();
        if ($this->view === 'articles') {
            $itemIds = $items->flatten()->pluck('id');
            $allPositions = OrderPosition::whereIn('order_item_id', $itemIds)
                ->orderBy('order_item_id')
                ->orderBy('sort_order')
                ->get();
        }

        $bausteine = SettingsService::bausteine($event->team_id);
        $allowedGruppen = PositionValidator::allowedGruppen($event->team_id);

        $articleMatches = $this->view === 'editor'
            ? ArticleSearchService::search($event->team_id, (string) ($this->newPosition['name'] ?? ''))
            : collect();

        return view('events::livewire.detail.orders', [
            'event'          => $event,
            'days'           => $event->days,
            'items'          => $items,
            'quoteItems'     => $quoteItems,
            'activeItem'     => $activeItem,
            'activeDay'      => $activeDay,
            'articleMatches' => $articleMatches,
            'allPositions'   => $allPositions,
            'positions'      => $positions,
            'bausteine'      => $bausteine,
            'allowedGruppen' => $allowedGruppen,
        ]);
    }
}
