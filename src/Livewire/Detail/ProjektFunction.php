<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Event;

class ProjektFunction extends Component
{
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render()
    {
        $event = Event::with(['days', 'bookings.location', 'scheduleItems'])
            ->findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);

        return view('events::livewire.detail.projekt-function', [
            'event' => $event,
        ]);
    }
}
