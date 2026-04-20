<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\QuoteItem;

class Calculation extends Component
{
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render()
    {
        $event = Event::with(['days' => fn($q) => $q->orderBy('sort_order')])->findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);

        $dayIds = $event->days->pluck('id');
        $quoteItems = QuoteItem::whereIn('event_day_id', $dayIds)->get()->groupBy('event_day_id');
        $orderItems = OrderItem::whereIn('event_day_id', $dayIds)->get()->groupBy('event_day_id');

        $totalRevenue = 0;
        $totalCost = 0;

        $rows = $event->days->map(function ($day) use ($quoteItems, $orderItems, &$totalRevenue, &$totalCost) {
            $revenue = (float) $quoteItems->get($day->id, collect())->sum('umsatz');
            $cost    = (float) $orderItems->get($day->id, collect())->sum('einkauf');
            $totalRevenue += $revenue;
            $totalCost    += $cost;
            return [
                'day'     => $day,
                'revenue' => $revenue,
                'cost'    => $cost,
                'margin'  => $revenue - $cost,
                'marginPct' => $revenue > 0 ? (($revenue - $cost) / $revenue) * 100 : 0,
            ];
        });

        return view('events::livewire.detail.calculation', [
            'event'        => $event,
            'rows'         => $rows,
            'totalRevenue' => $totalRevenue,
            'totalCost'    => $totalCost,
            'totalMargin'  => $totalRevenue - $totalCost,
            'marginPct'    => $totalRevenue > 0 ? (($totalRevenue - $totalCost) / $totalRevenue) * 100 : 0,
        ]);
    }
}
