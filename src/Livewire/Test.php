<?php

namespace Platform\Events\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Test extends Component
{
    public $testValue = 'Test';
    public $testNumber = 42;
    public $testBoolean = true;

    public function render()
    {
        $user = Auth::user();

        return view('events::livewire.test', [
            'user' => $user,
        ])->layout('platform::layouts.app');
    }

    public function testAction()
    {
        $this->dispatch('notifications:store', [
            'title' => 'Test erfolgreich',
            'message' => 'Die Test-Aktion wurde ausgeführt.',
            'notice_type' => 'success',
            'noticable_type' => \stdClass::class,
            'noticable_id' => 0,
        ]);
    }
}
