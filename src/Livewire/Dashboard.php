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

        return view('events::livewire.dashboard', [
            'currentDate' => now()->format('d.m.Y'),
            'totalEvents' => $totalEvents,
            'upcoming'    => $upcoming,
            'running'     => $running,
            'past'        => $past,
        ])->layout('platform::layouts.app');
    }
}
