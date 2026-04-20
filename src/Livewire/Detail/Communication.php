<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\AppNotification;
use Platform\Events\Models\EmailLog;
use Platform\Events\Models\Event;

class Communication extends Component
{
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function deleteEmail(int $emailId): void
    {
        EmailLog::where('event_id', $this->eventId)->where('id', $emailId)->delete();
    }

    public function render()
    {
        $event = Event::findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);

        $emails = EmailLog::where('event_id', $event->id)->orderByDesc('created_at')->get();

        // Event-bezogene Notifications: heuristisch ueber link enthaelt event_number
        $notifications = AppNotification::where('user_id', Auth::id())
            ->where(function ($q) use ($event) {
                $q->where('link', 'like', '%' . $event->slug . '%')
                  ->orWhere('body', 'like', '%' . $event->event_number . '%');
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('events::livewire.detail.communication', [
            'event'         => $event,
            'emails'        => $emails,
            'notifications' => $notifications,
        ]);
    }
}
