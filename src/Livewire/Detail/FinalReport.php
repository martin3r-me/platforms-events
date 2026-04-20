<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Event;

class FinalReport extends Component
{
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render()
    {
        $event = Event::with(['days', 'bookings.location', 'scheduleItems', 'notes', 'quotes', 'invoices'])
            ->findOrFail($this->eventId);

        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);

        return view('events::livewire.detail.final-report', [
            'event' => $event,
        ]);
    }
}
