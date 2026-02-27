<?php

namespace Platform\Events\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Dashboard extends Component
{
    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => null,
            'modelId' => null,
            'subject' => 'Events Dashboard',
            'description' => 'Übersicht des Events-Moduls',
            'url' => route('events.dashboard'),
            'source' => 'events.dashboard',
            'recipients' => [],
            'meta' => [
                'view_type' => 'dashboard',
            ],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        return view('events::livewire.dashboard', [
            'currentDate' => now()->format('d.m.Y'),
            'currentDay' => now()->format('l'),
        ])->layout('platform::layouts.app');
    }
}
