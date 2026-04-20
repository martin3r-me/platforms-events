<?php

use Platform\Events\Http\Controllers\ContractPdfController;
use Platform\Events\Http\Controllers\InvoicePdfController;
use Platform\Events\Http\Controllers\QuotePdfController;
use Platform\Events\Livewire\Articles;
use Platform\Events\Livewire\Dashboard;
use Platform\Events\Livewire\Detail;
use Platform\Events\Livewire\Manage;

Route::get('/', Dashboard::class)->name('events.dashboard');
Route::get('/liste', Manage::class)->name('events.manage');
Route::get('/va/{slug}', Detail::class)->name('events.show');
Route::get('/artikel', Articles::class)->name('events.articles');

// PDF-Downloads
Route::get('/va/{event}/angebot/{quoteId}/pdf', [QuotePdfController::class, 'download'])->name('events.quote.pdf');
Route::get('/va/{event}/vertrag/{contractId}/pdf', [ContractPdfController::class, 'download'])->name('events.contract.pdf');
Route::get('/va/{event}/rechnung/{invoiceId}/pdf', [InvoicePdfController::class, 'download'])->name('events.invoice.pdf');
