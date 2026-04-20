<?php

use Platform\Events\Livewire\Dashboard;
use Platform\Events\Livewire\Detail;
use Platform\Events\Livewire\Manage;

Route::get('/', Dashboard::class)->name('events.dashboard');
Route::get('/liste', Manage::class)->name('events.manage');
Route::get('/va/{slug}', Detail::class)->name('events.show');
