<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Events\Models\Quote;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Services\PdfService;

class PublicQuoteController extends Controller
{
    public function show(Request $request, string $token)
    {
        $quote = Quote::with('event.days')->where('token', $token)->firstOrFail();

        $quote->increment('view_count');
        $quote->update(['last_viewed_at' => now()]);

        $items = QuoteItem::whereIn('event_day_id', $quote->event->days->pluck('id'))
            ->with('posList')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('event_day_id');

        return view('events::public.quote', [
            'quote' => $quote,
            'event' => $quote->event,
            'items' => $items,
        ]);
    }

    public function respond(Request $request, string $token)
    {
        $quote = Quote::where('token', $token)->firstOrFail();
        $action = $request->input('action'); // accept | reject
        $note = (string) $request->input('note', '');

        if (!in_array($action, ['accept', 'reject'], true)) {
            return redirect()->back()->with('error', 'Ungültige Aktion.');
        }

        // Abgelaufene Angebote duerfen nicht mehr online zugesagt/abgelehnt werden.
        if ($quote->isExpired()) {
            return redirect()
                ->route('events.public.quote', ['token' => $token])
                ->with('error', 'Dieses Angebot ist abgelaufen. Bitte fordern Sie eine aktualisierte Version an.');
        }

        // Nur "Gesendet"-Angebote duerfen beantwortet werden.
        if ($quote->status !== 'sent') {
            return redirect()
                ->route('events.public.quote', ['token' => $token])
                ->with('error', 'Dieses Angebot kann nicht mehr beantwortet werden.');
        }

        $quote->update([
            'status'        => $action === 'accept' ? 'accepted' : 'rejected',
            'responded_at'  => now(),
            'response_note' => $note !== '' ? $note : null,
        ]);

        return redirect()->route('events.public.quote', ['token' => $token])->with('status', 'Vielen Dank für Ihre Antwort.');
    }
}
