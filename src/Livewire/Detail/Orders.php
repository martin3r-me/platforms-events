<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Article;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Models\QuoteItem;
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

    public array $newPosition = [
        'gruppe' => '', 'name' => '', 'anz' => '', 'anz2' => '',
        'uhrzeit' => '', 'bis' => '', 'inhalt' => '', 'gebinde' => '',
        'basis_ek' => 0, 'ek' => 0, 'preis' => 0, 'mwst' => '7%',
        'gesamt' => 0, 'bemerkung' => '',
    ];

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
        OrderItem::create([
            'team_id'      => $event->team_id,
            'user_id'      => Auth::id(),
            'event_day_id' => $this->itemEventDayId,
            'typ'          => $this->itemTyp,
            'status'       => $this->itemStatus,
            'lieferant'    => $this->itemLieferant,
            'sort_order'   => $maxSort + 1,
        ]);
        $this->showItemModal = false;
    }

    public function deleteItem(int $itemId): void
    {
        $event = $this->event();
        $item = OrderItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($itemId);
        if ($item) $item->delete();
    }

    public function updateItemStatus(string $status): void
    {
        if (!$this->activeItemId) return;
        $event = $this->event();
        $item = OrderItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))->find($this->activeItemId);
        if ($item) $item->update(['status' => $status]);
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
        if (in_array($key, ['uhrzeit', 'bis'], true)) {
            $this->autoComputeAnz2FromTime();
        }
        if (in_array($key, ['anz', 'anz2', 'preis', 'ek'], true)) {
            $this->autoComputeGesamt();
        }
        if ($key === 'gesamt') {
            $this->autoComputeEkFromGesamt();
        }
    }

    protected function autoComputeAnz2FromTime(): void
    {
        $von = trim((string) ($this->newPosition['uhrzeit'] ?? ''));
        $bis = trim((string) ($this->newPosition['bis'] ?? ''));
        if ($von === '' || $bis === '') return;
        $hours = $this->hoursDiff($von, $bis);
        if ($hours === null) return;
        $this->newPosition['anz2'] = (string) (fmod($hours, 1) == 0 ? (int) $hours : round($hours, 2));
        $this->autoComputeGesamt();
    }

    protected function hoursDiff(string $von, string $bis): ?float
    {
        try {
            $s = \Carbon\Carbon::createFromFormat('H:i', $von);
            $e = \Carbon\Carbon::createFromFormat('H:i', $bis);
            if ($e->lessThan($s)) $e->addDay();
            $minutes = abs($s->diffInMinutes($e));
            return round($minutes / 60.0, 2);
        } catch (\Throwable $ex) {
            return null;
        }
    }

    protected function autoComputeGesamt(): void
    {
        $anz = (float) ($this->newPosition['anz'] ?? 0);
        $anz2 = (float) ($this->newPosition['anz2'] ?? 0);
        // Fuer Bestellungen: EK statt VK als Multiplikator
        $preis = (float) ($this->newPosition['ek'] ?? ($this->newPosition['preis'] ?? 0));
        if ($anz <= 0 || $preis <= 0) return;
        $mult = $anz2 > 0 ? $anz * $anz2 : $anz;
        $this->newPosition['gesamt'] = round($mult * $preis, 2);
    }

    protected function autoComputeEkFromGesamt(): void
    {
        $anz = (float) ($this->newPosition['anz'] ?? 0);
        $anz2 = (float) ($this->newPosition['anz2'] ?? 0);
        $gesamt = (float) ($this->newPosition['gesamt'] ?? 0);
        if ($gesamt <= 0 || $anz <= 0) return;
        $mult = $anz2 > 0 ? $anz * $anz2 : $anz;
        if ($mult > 0) {
            $this->newPosition['ek'] = round($gesamt / $mult, 2);
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
            $pos->delete();
            $item = OrderItem::find($this->activeItemId);
            if ($item) $this->recalculateItem($item);
        }
    }

    protected function recalculateItem(OrderItem $item): void
    {
        $positions = $item->posList()->get();
        $item->update([
            'artikel'    => $positions->count(),
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

        $articleMatches = collect();
        $query = trim((string) ($this->newPosition['name'] ?? ''));
        if (mb_strlen($query) >= 2 && $this->view === 'editor') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
            $prefixLike = str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
            $articleMatches = Article::where('team_id', $event->team_id)
                ->where('is_active', true)
                ->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                      ->orWhere('article_number', 'like', $like)
                      ->orWhere('external_code', 'like', $like);
                })
                ->orderByRaw('CASE WHEN name LIKE ? THEN 0 WHEN article_number LIKE ? THEN 1 ELSE 2 END', [$prefixLike, $prefixLike])
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'article_number', 'name', 'gebinde', 'ek', 'vk', 'mwst']);
        }

        return view('events::livewire.detail.orders', [
            'event'          => $event,
            'days'           => $event->days,
            'items'          => $items,
            'quoteItems'     => $quoteItems,
            'activeItem'     => $activeItem,
            'activeDay'      => $activeDay,
            'articleMatches' => $articleMatches,
            'allPositions' => $allPositions,
            'positions'    => $positions,
            'bausteine'    => $bausteine,
        ]);
    }
}
