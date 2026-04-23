<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Events\Models\Event;
use Platform\Events\Models\FeedbackEntry;
use Platform\Events\Models\FeedbackLink;
use Platform\Events\Services\ActivityLogger;

class Feedback extends Component
{
    public int $eventId;

    public bool $showNewLink = false;
    public string $newLabel = '';
    public string $newAudience = 'participant';

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

    public function toggleNewLink(): void
    {
        $this->showNewLink = !$this->showNewLink;
        if (!$this->showNewLink) {
            $this->newLabel = '';
            $this->newAudience = 'participant';
        }
    }

    public function createLink(): void
    {
        if (trim($this->newLabel) === '') return;
        $event = $this->event();
        $link = FeedbackLink::create([
            'team_id'   => $event->team_id,
            'user_id'   => Auth::id(),
            'event_id'  => $event->id,
            'label'     => $this->newLabel,
            'audience'  => $this->newAudience,
            'token'     => Str::random(48),
            'is_active' => true,
        ]);
        ActivityLogger::log($event, 'feedback', "Feedback-Link '{$link->label}' ({$link->audience}) angelegt");
        $this->newLabel = '';
        $this->newAudience = 'participant';
        $this->showNewLink = false;
    }

    public function toggleActive(int $linkId): void
    {
        $link = FeedbackLink::where('event_id', $this->eventId)->find($linkId);
        if ($link) {
            $link->update(['is_active' => !$link->is_active]);
            $state = $link->is_active ? 'aktiviert' : 'deaktiviert';
            ActivityLogger::log($this->event(), 'feedback', "Feedback-Link '{$link->label}' {$state}");
        }
    }

    public function deleteLink(int $linkId): void
    {
        $link = FeedbackLink::where('event_id', $this->eventId)->where('id', $linkId)->first();
        if ($link) {
            $label = $link->label;
            $link->delete();
            ActivityLogger::log($this->event(), 'feedback', "Feedback-Link '{$label}' geloescht");
        }
    }

    public function render()
    {
        $event = Event::findOrFail($this->eventId);
        $links = FeedbackLink::where('event_id', $event->id)
            ->withCount('entries')
            ->orderByDesc('id')
            ->get();
        $entries = FeedbackEntry::where('event_id', $event->id)
            ->with('link')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $avg = [
            'overall'      => null,
            'location'     => null,
            'catering'     => null,
            'organization' => null,
        ];
        $totalEntries = $entries->count();
        if ($totalEntries > 0) {
            $avg = [
                'overall'      => round($entries->avg('rating_overall'), 1),
                'location'     => round($entries->avg('rating_location'), 1),
                'catering'     => round($entries->avg('rating_catering'), 1),
                'organization' => round($entries->avg('rating_organization'), 1),
            ];
        }

        return view('events::livewire.detail.feedback', [
            'event'   => $event,
            'links'   => $links,
            'entries' => $entries,
            'avg'     => $avg,
            'total'   => $totalEntries,
        ]);
    }
}
