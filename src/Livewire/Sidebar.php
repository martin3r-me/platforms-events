<?php

namespace Platform\Events\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Sidebar extends Component
{
    public function render()
    {
        $user = auth()->user();

        if (!$user) {
            return view('events::livewire.sidebar', []);
        }

        return view('events::livewire.sidebar', []);
    }
}
