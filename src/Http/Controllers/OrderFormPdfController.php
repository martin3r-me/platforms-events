<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;
use Platform\Events\Services\PdfService;

/**
 * Bestellschein (AUFTRAG) fuer einen externen Dienstleister als PDF.
 * Ein Bestellschein entspricht einer OrderItem (Dienstleister/Tag) mit ihren
 * Positionen; Layout kommt aus der editierbaren Vorlage (OrderFormRenderer).
 */
class OrderFormPdfController extends Controller
{
    public function download(Request $request, string $event, int $orderItemId)
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $eventModel = Event::resolveFromSlug($event, $team?->id);
        if (!$eventModel) {
            abort(404);
        }

        // OrderItem muss zu einem Event-Tag dieses Events gehoeren.
        $item = OrderItem::with(['posList', 'eventDay'])
            ->whereHas('eventDay', fn ($q) => $q->where('event_id', $eventModel->id))
            ->findOrFail($orderItemId);

        $filename = 'Bestellschein-' . $eventModel->slug . '-' . Str::slug($item->recipientName() ?: $item->typ) . '.pdf';

        return PdfService::render('events::pdf.order-form', [
            'event' => $eventModel,
            'item'  => $item,
        ], $filename);
    }
}
