<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Activity;
use Platform\Events\Models\Booking;
use Platform\Events\Models\Contract;
use Platform\Events\Models\Event;
use Platform\Events\Models\FeedbackEntry;
use Platform\Events\Models\FeedbackLink;
use Platform\Events\Models\Invoice;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\PickList;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\ScheduleItem;

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
        $event = Event::findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);

        $activities = Activity::where('event_id', $event->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        // Bereichs-Zuordnung pro Activity-type
        $typeToSection = [
            'created'   => 'basis',
            'status'    => 'basis',
            'updated'   => 'basis',
            'signature' => 'basis',
            'quote'     => 'angebot',
            'order'     => 'bestellung',
            'contract'  => 'vertrag',
            'invoice'   => 'rechnung',
            'picklist'  => 'packliste',
            'booking'   => 'raeume',
            'schedule'  => 'ablauf',
            'feedback'  => 'feedback',
            'note'      => 'basis',
        ];

        $sections = [
            'basis'      => ['label' => 'Basis',        'icon' => 'heroicon-o-home',                    'bg' => 'bg-blue-50',   'text' => 'text-blue-700',   'badge' => 'bg-blue-50 text-blue-700 border-blue-200'],
            'raeume'     => ['label' => 'Räume',        'icon' => 'heroicon-o-building-office-2',       'bg' => 'bg-green-50',  'text' => 'text-green-700',  'badge' => 'bg-green-50 text-green-700 border-green-200'],
            'angebot'    => ['label' => 'Angebote',     'icon' => 'heroicon-o-document-duplicate',      'bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'badge' => 'bg-purple-50 text-purple-700 border-purple-200'],
            'bestellung' => ['label' => 'Bestellungen', 'icon' => 'heroicon-o-shopping-cart',           'bg' => 'bg-orange-50', 'text' => 'text-orange-700', 'badge' => 'bg-orange-50 text-orange-700 border-orange-200'],
            'ablauf'     => ['label' => 'Ablauf',       'icon' => 'heroicon-o-bars-3',                  'bg' => 'bg-cyan-50',   'text' => 'text-cyan-700',   'badge' => 'bg-cyan-50 text-cyan-700 border-cyan-200'],
            'feedback'   => ['label' => 'Feedback',     'icon' => 'heroicon-o-star',                    'bg' => 'bg-pink-50',   'text' => 'text-pink-700',   'badge' => 'bg-pink-50 text-pink-700 border-pink-200'],
        ];

        // Jeweils nur die neueste Activity pro Bereich – die Kachel zeigt
        // Count + letzte Aenderung, keine Timeline.
        $sectionActivities = [];
        foreach ($sections as $key => $_) {
            $latest = $activities
                ->first(fn ($a) => ($typeToSection[$a->type] ?? 'basis') === $key);
            $sectionActivities[$key] = $latest ? collect([$latest]) : collect();
        }

        // Counts
        $days = $event->days;
        $dayIds = $days->pluck('id');
        $counts = [
            'basis'      => 1, // Event-Basis existiert immer
            'raeume'     => Booking::where('event_id', $event->id)->count(),
            'angebot'    => QuoteItem::whereIn('event_day_id', $dayIds)->count(),
            'bestellung' => OrderItem::whereIn('event_day_id', $dayIds)->count(),
            'ablauf'     => ScheduleItem::where('event_id', $event->id)->count(),
            'feedback'   => FeedbackEntry::where('event_id', $event->id)->count(),
        ];

        // Status-Badges fuer jede Kachel
        $sectionStatus = [
            'basis'      => $event->status === 'Vertrag' || $event->status === 'Definitiv' ? 'ok' : ($event->status === 'Storno' ? 'error' : 'pending'),
            'raeume'     => $counts['raeume'] > 0 ? 'ok' : 'pending',
            'angebot'    => $counts['angebot'] > 0 ? 'pending' : 'empty',
            'bestellung' => $counts['bestellung'] > 0 ? 'pending' : 'empty',
            'ablauf'     => $counts['ablauf'] > 0 ? 'ok' : 'pending',
            'feedback'   => $counts['feedback'] > 0 ? 'ok' : 'empty',
        ];

        $lastChange = $activities->first()?->created_at ?: $event->updated_at;

        return view('events::livewire.detail.activities', [
            'event'              => $event,
            'activities'         => $activities,
            'sections'           => $sections,
            'sectionActivities'  => $sectionActivities,
            'counts'             => $counts,
            'sectionStatus'      => $sectionStatus,
            'lastChange'         => $lastChange,
            'typeToSection'      => $typeToSection,
        ]);
    }
}
