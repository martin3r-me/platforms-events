<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Event;
use Platform\Events\Models\EventNote;
use Platform\Events\Models\Invoice;
use Platform\Events\Models\MrFieldConfig;
use Platform\Events\Models\Quote;
use Platform\Events\Models\QuoteItem;

class FinalReport extends Component
{
    public int $eventId;

    public string $internalRating = '';
    public string $customerSatisfaction = '';
    public string $rebookingRecommendation = '';

    public string $newNote = '';

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $event = $this->event();
        $this->internalRating = (string) ($event->internal_rating ?? '');
        $this->customerSatisfaction = (string) ($event->customer_satisfaction ?? '');
        $this->rebookingRecommendation = (string) ($event->rebooking_recommendation ?? '');
    }

    protected function event(): Event
    {
        $event = Event::findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);
        return $event;
    }

    public function updatedInternalRating(): void
    {
        $this->event()->update(['internal_rating' => $this->internalRating ?: null]);
    }

    public function updatedCustomerSatisfaction(): void
    {
        $this->event()->update(['customer_satisfaction' => $this->customerSatisfaction ?: null]);
    }

    public function updatedRebookingRecommendation(): void
    {
        $this->event()->update(['rebooking_recommendation' => $this->rebookingRecommendation ?: null]);
    }

    public function addNote(): void
    {
        if (trim($this->newNote) === '') return;
        $event = $this->event();
        EventNote::create([
            'team_id'  => $event->team_id,
            'user_id'  => Auth::id(),
            'event_id' => $event->id,
            'type'     => 'schlussbericht',
            'text'     => trim($this->newNote),
        ]);
        $this->newNote = '';
    }

    public function deleteNote(string $uuid): void
    {
        $note = EventNote::where('event_id', $this->eventId)->where('uuid', $uuid)->first();
        if ($note && $note->user_id === Auth::id()) {
            $note->delete();
        }
    }

    public function render()
    {
        $event = Event::with([
            'days',
            'bookings.location',
            'scheduleItems',
            'notes',
            'quotes',
            'invoices',
        ])->findOrFail($this->eventId);

        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);

        $dayIds = $event->days->pluck('id');

        // Umsatz pro Event-Tag (QuoteItem haengt am event_day, nicht am Quote-Dokument)
        $quoteItems = QuoteItem::whereIn('event_day_id', $dayIds)->get();
        $umsatzPerDay = $quoteItems->groupBy('event_day_id')
            ->map(fn ($items) => (float) $items->sum('umsatz'));

        // Zeilen fuer die Umsatztabelle (pro Tag mit Label + Summe)
        $quotes = $event->days->map(function ($day) use ($umsatzPerDay) {
            return (object) [
                'id'    => $day->id,
                'title' => ($day->label ?: $day->day_of_week) . ' · ' . ($day->datum?->format('d.m.Y') ?: '—'),
            ];
        });
        $quoteSums = $umsatzPerDay->toArray();
        $totalRevenue = (float) $quoteItems->sum('umsatz');

        // Rechnungsstatus
        $invoices = Invoice::where('event_id', $event->id)->orderBy('invoice_number')->get();
        $paid = (float) $invoices->where('status', 'paid')->sum('brutto');
        $open = (float) $invoices->whereIn('status', ['sent', 'overdue'])->sum('brutto');

        $mrConfigs = MrFieldConfig::where('team_id', $event->team_id)
            ->orderBy('sort_order')->get();
        $mrData = is_array($event->mr_data) ? $event->mr_data : [];

        $schlussNotes = EventNote::where('event_id', $event->id)
            ->where('type', 'schlussbericht')
            ->orderByDesc('created_at')
            ->get();

        $roomsUnique = $event->bookings->pluck('raum')->filter()->unique()->count();

        return view('events::livewire.detail.final-report', [
            'event'        => $event,
            'quotes'       => $quotes,
            'quoteSums'    => $quoteSums,
            'totalRevenue' => $totalRevenue,
            'invoices'     => $invoices,
            'paid'         => $paid,
            'open'         => $open,
            'mrConfigs'    => $mrConfigs,
            'mrData'       => $mrData,
            'schlussNotes' => $schlussNotes,
            'roomsUnique'  => $roomsUnique,
            'currentUserId'=> Auth::id(),
            'currentUser'  => Auth::user()?->name,
        ]);
    }
}
