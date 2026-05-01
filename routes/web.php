<?php

use Platform\Events\Http\Controllers\ContractAssetController;
use Platform\Events\Http\Controllers\ContractPdfController;
use Platform\Events\Http\Controllers\InvoicePdfController;
use Platform\Events\Http\Controllers\QuotePdfController;
use Platform\Events\Http\Controllers\ReportPdfController;
use Platform\Events\Http\Controllers\SignatureController;
use Platform\Events\Livewire\Articles;
use Platform\Events\Livewire\Dashboard;
use Platform\Events\Livewire\Detail;
use Platform\Events\Livewire\Manage;
use Platform\Events\Livewire\Settings;

Route::get('/', Dashboard::class)->name('events.dashboard');
Route::get('/liste', Manage::class)->name('events.manage');
Route::get('/va/{slug}', Detail::class)->name('events.show');
Route::get('/pakete', Articles::class)->name('events.articles');
Route::get('/einstellungen', Settings::class)->name('events.settings');

// Contract-Editor Asset-Upload (TinyMCE)
Route::post('/contract-assets/upload', [ContractAssetController::class, 'upload'])->name('events.contract-assets.upload');

// Signatur-Flow
Route::post('/va/{event}/sign', [SignatureController::class, 'sign'])->name('events.sign');
Route::delete('/va/{event}/sign/{role}', [SignatureController::class, 'reset'])->name('events.sign.reset');

// PDF-Downloads
Route::get('/va/{event}/angebot/{quoteId}/pdf', [QuotePdfController::class, 'download'])->name('events.quote.pdf');
Route::get('/va/{event}/vertrag/{contractId}/pdf', [ContractPdfController::class, 'download'])->name('events.contract.pdf');
Route::get('/va/{event}/rechnung/{invoiceId}/pdf', [InvoicePdfController::class, 'download'])->name('events.invoice.pdf');
Route::get('/va/{event}/projekt-function/pdf', [ReportPdfController::class, 'projektFunction'])->name('events.projekt-function.pdf');
Route::get('/va/{event}/schlussbericht/pdf', [ReportPdfController::class, 'finalReport'])->name('events.final-report.pdf');
