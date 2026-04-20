<?php

use Illuminate\Support\Facades\Route;
use Platform\Events\Http\Controllers\PublicContractController;
use Platform\Events\Http\Controllers\PublicInvoiceController;
use Platform\Events\Http\Controllers\PublicQuoteController;

/*
|--------------------------------------------------------------------------
| Public Token-Routes
|--------------------------------------------------------------------------
|
| Token-basierte oeffentliche Routen – kein Auth. Fuer Kunden-Ansicht
| von Angeboten, Vertraegen, Rechnungen, Pick-Listen und Feedback.
|
*/

Route::get('/angebot/{token}', [PublicQuoteController::class, 'show'])->name('events.public.quote');
Route::post('/angebot/{token}/respond', [PublicQuoteController::class, 'respond'])->name('events.public.quote.respond');

Route::get('/vertrag/{token}', [PublicContractController::class, 'show'])->name('events.public.contract');
Route::post('/vertrag/{token}/respond', [PublicContractController::class, 'respond'])->name('events.public.contract.respond');

Route::get('/rechnung/{token}', [PublicInvoiceController::class, 'show'])->name('events.public.invoice');
