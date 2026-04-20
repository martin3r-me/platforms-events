<?php

use Platform\Events\Livewire\Dashboard;

Route::get('/', Dashboard::class)->name('events.dashboard');
