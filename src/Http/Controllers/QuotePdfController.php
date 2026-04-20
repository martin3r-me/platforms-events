<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Events\Models\Event;
use Platform\Events\Models\Quote;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Services\PdfService;

class QuotePdfController extends Controller
{
    public function download(Request $request, string $event, int $quoteId)
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $eventModel = Event::resolveFromSlug($event, $team?->id);
        if (!$eventModel) {
            abort(404);
        }

        $quote = Quote::where('event_id', $eventModel->id)->findOrFail($quoteId);

        $items = QuoteItem::whereIn('event_day_id', $eventModel->days->pluck('id'))
            ->with('posList')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('event_day_id');

        return PdfService::render('events::pdf.quote', [
            'event' => $eventModel,
            'quote' => $quote,
            'items' => $items,
            'days'  => $eventModel->days()->orderBy('sort_order')->get(),
        ], 'Angebot-' . $eventModel->slug . '-v' . $quote->version . '.pdf');
    }
}
