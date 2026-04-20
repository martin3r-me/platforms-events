<?php

use Illuminate\Support\Facades\Route;
use Platform\Events\Http\Controllers\EmailTrackController;
use Platform\Events\Http\Controllers\PublicContractController;
use Platform\Events\Http\Controllers\PublicFeedbackController;
use Platform\Events\Http\Controllers\PublicInvoiceController;
use Platform\Events\Http\Controllers\PublicPickListController;
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

Route::get('/picking/{token}', [PublicPickListController::class, 'show'])->name('events.public.picklist');
Route::patch('/picking/{token}/items/{itemId}', [PublicPickListController::class, 'updateItem'])->name('events.public.picklist.item');
Route::get('/picking/{token}/progress', [PublicPickListController::class, 'progress'])->name('events.public.picklist.progress');

Route::get('/email/track/{token}', [EmailTrackController::class, 'track'])->name('events.public.email.track');

Route::get('/feedback/{token}', [PublicFeedbackController::class, 'show'])->name('events.public.feedback');
Route::post('/feedback/{token}', [PublicFeedbackController::class, 'submit'])->name('events.public.feedback.submit');
