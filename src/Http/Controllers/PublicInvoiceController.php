<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Events\Models\Invoice;

class PublicInvoiceController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invoice = Invoice::with(['event', 'items'])->where('token', $token)->firstOrFail();
        $invoice->increment('view_count');
        $invoice->update(['last_viewed_at' => now()]);

        return view('events::public.invoice', [
            'invoice' => $invoice,
            'event'   => $invoice->event,
        ]);
    }
}
