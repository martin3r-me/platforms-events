<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Events\Models\Event;
use Platform\Events\Models\Invoice;
use Platform\Events\Services\PdfService;

class InvoicePdfController extends Controller
{
    public function download(Request $request, string $event, int $invoiceId)
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $eventModel = Event::resolveFromSlug($event, $team?->id);
        if (!$eventModel) abort(404);

        $invoice = Invoice::with('items')->where('event_id', $eventModel->id)->findOrFail($invoiceId);

        return PdfService::render('events::pdf.invoice', [
            'event'   => $eventModel,
            'invoice' => $invoice,
        ], 'Rechnung-' . $invoice->invoice_number . '.pdf');
    }
}
