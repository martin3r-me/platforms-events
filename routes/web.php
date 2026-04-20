<?php

use Platform\Events\Livewire\Articles;
use Platform\Events\Livewire\Dashboard;
use Platform\Events\Livewire\Detail;
use Platform\Events\Livewire\Manage;

use Platform\Events\Http\Controllers\QuotePdfController;

Route::get('/', Dashboard::class)->name('events.dashboard');
Route::get('/liste', Manage::class)->name('events.manage');
Route::get('/va/{slug}', Detail::class)->name('events.show');
Route::get('/artikel', Articles::class)->name('events.articles');

// PDF-Downloads
Route::get('/va/{event}/angebot/{quoteId}/pdf', [QuotePdfController::class, 'download'])->name('events.quote.pdf');
