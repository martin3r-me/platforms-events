<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Models\QuoteItem;

class Orders extends Component
{
    public int $eventId;
    public ?int $activeItemId = null;

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

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
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
        $this->activeItemId = $itemId;
        $this->resetNewPosition();
        $this->dispatch('scroll-to-positions');
    }

    public function closePositions(): void
    {
        $this->activeItemId = null;
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

        return view('events::livewire.detail.orders', [
            'event'       => $event,
            'days'        => $event->days,
            'items'       => $items,
            'quoteItems'  => $quoteItems,
            'activeItem'  => $activeItem,
            'positions'   => $positions,
        ]);
    }
}
