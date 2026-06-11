<?php

namespace Platform\Events\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Booking;
use Platform\Events\Models\Event;
use Platform\Events\Models\Quote;

class Dashboard extends Component
{
    public function rendered()
    {
        $this->dispatch('comms', [
            'model'       => null,
            'modelId'     => null,
            'subject'     => 'Events Dashboard',
            'description' => 'Übersicht des Events-Moduls',
            'url'         => route('events.dashboard'),
            'source'      => 'events.dashboard',
            'recipients'  => [],
            'meta'        => [
                'view_type' => 'dashboard',
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user?->currentTeam;

        $base = Event::query();
        if ($team) {
            $base->where('team_id', $team->id);
        }

        $today = now()->toDateString();

        $totalEvents = (clone $base)->count();
        $upcoming    = (clone $base)
            ->where(function ($q) use ($today) {
                $q->where('end_date', '>=', $today)->orWhereNull('end_date');
            })
            ->where(function ($q) use ($today) {
                $q->where('start_date', '>=', $today)->orWhereNull('start_date');
            })
            ->count();
        $running     = (clone $base)
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->where('end_date', '>=', $today)->orWhereNull('end_date');
            })
            ->count();
        $past        = (clone $base)->where('end_date', '<', $today)->count();

        // Anstehende Veranstaltungen: laufende + zukünftige, nach Startdatum sortiert
        $upcomingEvents = (clone $base)
            ->where(function ($q) use ($today) {
                $q->where('end_date', '>=', $today)->orWhereNull('end_date');
            })
            ->with(['days' => fn ($q) => $q->select('id', 'event_id', 'pers_von', 'pers_bis')])
            ->orderBy('start_date')
            ->limit(20)
            ->get();

        // CRM-Resolver für Kunden-Labels
        $crmResolver = app()->bound(\Platform\Core\Contracts\CrmCompanyResolverInterface::class)
            ? app(\Platform\Core\Contracts\CrmCompanyResolverInterface::class)
            : null;

        $customerLabels = [];
        foreach ($upcomingEvents as $e) {
            $label = null;
            if ($e->crm_company_id && $crmResolver) {
                $label = $crmResolver->displayName($e->crm_company_id);
            }
            $customerLabels[$e->id] = $label ?: ($e->customer ?: null);
        }

        return view('events::livewire.dashboard', [
            'currentDate'    => now()->format('d.m.Y'),
            'totalEvents'    => $totalEvents,
            'upcoming'       => $upcoming,
            'running'        => $running,
            'past'           => $past,
            'upcomingEvents' => $upcomingEvents,
            'customerLabels' => $customerLabels,
            'resubmission'   => $team ? $this->buildResubmission($team->id) : ['options' => collect(), 'followUps' => collect(), 'quotes' => collect()],
        ])->layout('platform::layouts.app');
    }

    /** Event-Status, die keine Wiedervorlage mehr brauchen. */
    protected const INACTIVE_STATUSES = ['Storno', 'Abgeschlossen'];

    /** Horizont in Tagen: Fristen bis dahin (plus alles Ueberfaellige) erscheinen im Cockpit. */
    protected const RESUBMISSION_HORIZON_DAYS = 7;

    /**
     * Wiedervorlage-Cockpit: drei Fristtypen in einem Datenpaket.
     *
     *  1. Ablaufende Raum-Optionsfristen (Booking.option_until)
     *  2. Faellige Follow-ups (Event.follow_up_date)
     *  3. Ablaufende versendete Angebote (Quote.valid_until, status=sent)
     *
     * Jeweils inkl. Ueberfaelligem; Events in Storno/Abgeschlossen ausgenommen.
     *
     * @return array{options: \Illuminate\Support\Collection, followUps: \Illuminate\Support\Collection, quotes: \Illuminate\Support\Collection}
     */
    protected function buildResubmission(int $teamId): array
    {
        $horizon = now()->addDays(self::RESUBMISSION_HORIZON_DAYS)->toDateString();

        $options = Booking::query()
            ->where('team_id', $teamId)
            ->whereNotNull('option_until')
            ->whereDate('option_until', '<=', $horizon)
            ->where('optionsrang', 'like', '%Option%')
            ->whereHas('event', fn ($q) => $q->whereNotIn('status', self::INACTIVE_STATUSES))
            ->with(['event:id,name,event_number,status', 'location:id,name,kuerzel'])
            ->orderBy('option_until')
            ->limit(15)
            ->get();

        $followUps = Event::query()
            ->where('team_id', $teamId)
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<=', $horizon)
            ->whereNotIn('status', self::INACTIVE_STATUSES)
            ->orderBy('follow_up_date')
            ->limit(15)
            ->get(['id', 'name', 'event_number', 'status', 'follow_up_date', 'follow_up_note']);

        $quotes = Quote::query()
            ->where('team_id', $teamId)
            ->where('is_current', true)
            ->where('status', 'sent')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<=', $horizon)
            ->whereHas('event', fn ($q) => $q->whereNotIn('status', self::INACTIVE_STATUSES))
            ->with('event:id,name,event_number,status')
            ->orderBy('valid_until')
            ->limit(15)
            ->get();

        return ['options' => $options, 'followUps' => $followUps, 'quotes' => $quotes];
    }
}
