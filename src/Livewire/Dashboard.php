<?php

namespace Platform\Events\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Events\Models\Event;

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
        ])->layout('platform::layouts.app');
    }
}
