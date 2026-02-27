<?php

use Platform\Events\Livewire\Dashboard;
use Platform\Events\Livewire\Test;
use Platform\Events\Livewire\Sidebar;

Route::get('/', Dashboard::class)->name('events.dashboard');
Route::get('/test', Test::class)->name('events.test');
