<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Events\Models\Event;
use Platform\Events\Services\PdfService;
use Platform\Events\Services\ProjektFunctionData;

class ReportPdfController extends Controller
{
    public function projektFunction(Request $request, string $event)
    {
        $e = $this->resolveEvent($event);
        $mode = $request->query('mode') === 'manager' ? 'manager' : 'kitchen';

        $data = ProjektFunctionData::build($e);
        $data['showPrices'] = $mode === 'manager';
        $data['mode'] = $mode;

        $suffix = $mode === 'manager' ? '-PL' : '';
        $filename = 'Projekt-Function' . $suffix . '-' . $e->slug . '.pdf';

        if ($request->boolean('preview')) {
            return PdfService::stream('events::pdf.projekt-function', $data, $filename);
        }
        return PdfService::render('events::pdf.projekt-function', $data, $filename);
    }

    /**
     * Function Sheet / Tagesregie: das Betriebsdokument fuer Serviceleitung
     * und Technik am Veranstaltungstag — pro Tag Raumbuchungen (inkl.
     * Bestuhlung, Zeiten, PAX), Ablaufplan und Absprachen/Notizen.
     */
    public function functionSheet(Request $request, string $event)
    {
        $e = $this->resolveEvent($event);

        $days = $e->days->sortBy('sort_order')->values();
        $dayDates = $days->map(fn ($d) => $d->datum?->format('Y-m-d'))->filter()->values()->all();

        $dateKey = fn ($v) => substr((string) $v, 0, 10);
        $bookingsByDate = $e->bookings->sortBy('sort_order')->groupBy(fn ($b) => $dateKey($b->datum));
        $scheduleByDate = $e->scheduleItems->sortBy([['datum', 'asc'], ['sort_order', 'asc']])->groupBy(fn ($s) => $dateKey($s->datum));

        // Buchungen/Ablauf-Eintraege an Daten ohne EventDay (z. B. Vortag) — eigene Sektion.
        $orphanBookings = $e->bookings->filter(fn ($b) => !in_array($dateKey($b->datum), $dayDates, true))->sortBy('datum')->values();
        $orphanSchedule = $e->scheduleItems->filter(fn ($s) => !in_array($dateKey($s->datum), $dayDates, true))->sortBy('datum')->values();

        $data = [
            'event'          => $e,
            'days'           => $days,
            'bookingsByDate' => $bookingsByDate,
            'scheduleByDate' => $scheduleByDate,
            'orphanBookings' => $orphanBookings,
            'orphanSchedule' => $orphanSchedule,
            'notesByType'    => $e->notes->sortBy('created_at')->groupBy('type'),
            'generated_at'   => now()->format('d.m.Y H:i'),
            'generated_by'   => Auth::user()?->name ?? '',
        ];

        $filename = 'Function-Sheet-' . $e->slug . '.pdf';

        if ($request->boolean('preview')) {
            return PdfService::stream('events::pdf.function-sheet', $data, $filename);
        }
        return PdfService::render('events::pdf.function-sheet', $data, $filename);
    }

    public function finalReport(Request $request, string $event)
    {
        $e = $this->resolveEvent($event);
        $data = ['event' => $e];
        $filename = 'Schlussbericht-' . $e->slug . '.pdf';

        if ($request->boolean('preview')) {
            return PdfService::stream('events::pdf.final-report', $data, $filename);
        }
        return PdfService::render('events::pdf.final-report', $data, $filename);
    }

    protected function resolveEvent(string $slug): Event
    {
        $team = Auth::user()->currentTeam;
        $event = Event::resolveFromSlug($slug, $team?->id);
        if (!$event) abort(404);
        $event->load([
            'days.quoteItems.posList',
            'bookings.location',
            'scheduleItems',
            'notes',
            'quotes',
            'invoices',
        ]);
        return $event;
    }
}
