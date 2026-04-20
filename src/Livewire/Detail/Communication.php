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

    public bool $compose = false;
    public ?int $selectedId = null;

    public string $newTo = '';
    public string $newSubject = '';
    public string $newBody = '';
    public string $newType = 'quote';

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    protected function event(): Event
    {
        $event = Event::findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);
        return $event;
    }

    public function toggleCompose(): void
    {
        $this->compose = !$this->compose;
    }

    public function resetCompose(): void
    {
        $this->compose = false;
        $this->newTo = '';
        $this->newSubject = '';
        $this->newBody = '';
        $this->newType = 'quote';
    }

    public function select(int $id): void
    {
        $this->selectedId = $this->selectedId === $id ? null : $id;
    }

    public function send(): void
    {
        if (trim($this->newTo) === '' || trim($this->newSubject) === '') return;
        $event = $this->event();
        EmailLog::create([
            'team_id'  => $event->team_id,
            'user_id'  => Auth::id(),
            'event_id' => $event->id,
            'type'     => $this->newType,
            'to'       => $this->newTo,
            'subject'  => $this->newSubject,
            'body'     => $this->newBody,
            'status'   => 'sent',
            'sent_by'  => Auth::user()?->name,
        ]);
        $this->resetCompose();
    }

    public function deleteEmail(int $emailId): void
    {
        EmailLog::where('event_id', $this->eventId)->where('id', $emailId)->delete();
        if ($this->selectedId === $emailId) $this->selectedId = null;
    }

    public function render()
    {
        $event = $this->event();
        $emails = EmailLog::where('event_id', $event->id)->orderByDesc('created_at')->get();

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
