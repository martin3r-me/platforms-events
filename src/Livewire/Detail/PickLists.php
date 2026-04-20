<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Models\PickItem;
use Platform\Events\Models\PickList;
use Platform\Events\Services\ActivityLogger;

class PickLists extends Component
{
    public int $eventId;
    public ?int $activeListId = null;

    public bool $showCreateModal = false;
    public string $newTitle = '';

    public bool $showItemsModal = false;
    public array $newItem = [
        'name' => '', 'gruppe' => '', 'quantity' => 1,
        'gebinde' => '', 'lagerort' => '',
    ];

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->activeListId = PickList::where('event_id', $eventId)->latest('id')->value('id');
    }

    protected function event(): Event
    {
        $event = Event::findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);
        return $event;
    }

    public function openCreate(): void
    {
        $this->newTitle = '';
        $this->showCreateModal = true;
    }

    public function createList(): void
    {
        if (trim($this->newTitle) === '') return;
        $event = $this->event();
        $list = PickList::create([
            'team_id'    => $event->team_id,
            'user_id'    => Auth::id(),
            'event_id'   => $event->id,
            'title'      => $this->newTitle,
            'status'     => 'open',
            'token'      => Str::random(48),
            'created_by' => Auth::user()?->name,
        ]);
        $this->activeListId = $list->id;
        $this->showCreateModal = false;
        $this->newTitle = '';
        ActivityLogger::log($event, 'picklist', "Packliste „{$list->title}“ angelegt");
    }

    public function selectList(int $listId): void
    {
        if (PickList::where('event_id', $this->eventId)->where('id', $listId)->exists()) {
            $this->activeListId = $listId;
        }
    }

    public function setStatus(string $status): void
    {
        if (!$this->activeListId) return;
        $list = PickList::find($this->activeListId);
        if ($list && $list->event_id === $this->eventId) {
            $old = $list->status;
            $list->update(['status' => $status]);
            ActivityLogger::log($this->event(), 'picklist', "Packliste-Status: „{$old}“ → „{$status}“");
        }
    }

    public function generateFromOrders(): void
    {
        $event = $this->event();
        $orderItems = OrderItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))
            ->with('posList')
            ->get();

        if ($orderItems->isEmpty()) return;

        $list = PickList::create([
            'team_id'    => $event->team_id,
            'user_id'    => Auth::id(),
            'event_id'   => $event->id,
            'title'      => 'Generiert aus Bestellungen ' . now()->format('d.m.Y'),
            'status'     => 'open',
            'token'      => Str::random(48),
            'created_by' => Auth::user()?->name,
        ]);

        $sort = 0;
        foreach ($orderItems as $orderItem) {
            foreach ($orderItem->posList as $pos) {
                PickItem::create([
                    'team_id'      => $event->team_id,
                    'user_id'      => Auth::id(),
                    'pick_list_id' => $list->id,
                    'name'         => $pos->name,
                    'quantity'     => (int) (((float) $pos->anz) ?: 1),
                    'gebinde'      => $pos->gebinde,
                    'gruppe'       => $pos->gruppe ?: $orderItem->typ,
                    'status'       => 'open',
                    'sort_order'   => $sort++,
                ]);
            }
        }

        $this->activeListId = $list->id;
        ActivityLogger::log($event, 'picklist', "Packliste generiert aus Bestellungen ({$sort} Positionen)");
    }

    public function deleteList(int $listId): void
    {
        $list = PickList::where('event_id', $this->eventId)->find($listId);
        if ($list) {
            $list->delete();
            if ($this->activeListId === $listId) {
                $this->activeListId = PickList::where('event_id', $this->eventId)->latest('id')->value('id');
            }
        }
    }

    public function openItems(): void
    {
        if (!$this->activeListId) return;
        $this->resetNewItem();
        $this->showItemsModal = true;
    }

    protected function resetNewItem(): void
    {
        $this->newItem = [
            'name' => '', 'gruppe' => '', 'quantity' => 1,
            'gebinde' => '', 'lagerort' => '',
        ];
    }

    public function addItem(): void
    {
        if (!$this->activeListId || trim($this->newItem['name']) === '') return;
        $list = PickList::find($this->activeListId);
        if (!$list) return;
        $maxSort = (int) PickItem::where('pick_list_id', $list->id)->max('sort_order');

        PickItem::create([
            'team_id'      => $list->team_id,
            'user_id'      => Auth::id(),
            'pick_list_id' => $list->id,
            'name'         => $this->newItem['name'],
            'gruppe'       => $this->newItem['gruppe'],
            'quantity'     => (int) $this->newItem['quantity'],
            'gebinde'      => $this->newItem['gebinde'],
            'lagerort'     => $this->newItem['lagerort'],
            'status'       => 'open',
            'sort_order'   => $maxSort + 1,
        ]);

        $this->resetNewItem();
    }

    public function deleteItem(int $itemId): void
    {
        if (!$this->activeListId) return;
        PickItem::where('pick_list_id', $this->activeListId)->where('id', $itemId)->delete();
    }

    public function toggleItemStatus(int $itemId): void
    {
        if (!$this->activeListId) return;
        $item = PickItem::where('pick_list_id', $this->activeListId)->find($itemId);
        if (!$item) return;
        $next = match ($item->status) {
            'open'   => 'picked',
            'picked' => 'packed',
            'packed' => 'loaded',
            default  => 'open',
        };
        $item->update([
            'status'    => $next,
            'picked_by' => Auth::user()?->name,
            'picked_at' => now(),
        ]);
    }

    public function render()
    {
        $event = Event::findOrFail($this->eventId);
        $lists = PickList::where('event_id', $event->id)->orderByDesc('id')->get();

        $active = null;
        $items = collect();
        if ($this->activeListId) {
            $active = PickList::find($this->activeListId);
            if ($active) {
                $items = $active->items()->orderBy('sort_order')->get();
            }
        }

        return view('events::livewire.detail.pick-lists', [
            'event'  => $event,
            'lists'  => $lists,
            'active' => $active,
            'items'  => $items,
        ]);
    }
}
