<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Activity;
use Platform\Events\Models\Event;

class Activities extends Component
{
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function delete(string $uuid): void
    {
        $user = Auth::user();
        $event = Event::find($this->eventId);
        if (!$event || $event->team_id !== $user->currentTeam?->id) {
            return;
        }

        Activity::where('event_id', $event->id)->where('uuid', $uuid)->delete();
    }

    public function render()
    {
        $activities = Activity::where('event_id', $this->eventId)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return view('events::livewire.detail.activities', [
            'activities' => $activities,
        ]);
    }
}
