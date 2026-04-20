<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Events\Models\Event;
use Platform\Events\Services\PdfService;

class ReportPdfController extends Controller
{
    public function projektFunction(Request $request, string $event)
    {
        $e = $this->resolveEvent($event);
        return PdfService::render('events::pdf.projekt-function', [
            'event' => $e,
        ], 'ProjektFunction-' . $e->slug . '.pdf');
    }

    public function finalReport(Request $request, string $event)
    {
        $e = $this->resolveEvent($event);
        return PdfService::render('events::pdf.final-report', [
            'event' => $e,
        ], 'Schlussbericht-' . $e->slug . '.pdf');
    }

    protected function resolveEvent(string $slug): Event
    {
        $team = Auth::user()->currentTeam;
        $event = Event::resolveFromSlug($slug, $team?->id);
        if (!$event) abort(404);
        $event->load(['days', 'bookings.location', 'scheduleItems', 'notes', 'quotes', 'invoices']);
        return $event;
    }
}
