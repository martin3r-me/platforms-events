<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Events\Models\Event;

/**
 * Liefert die Veranstaltungen des aktuellen Teams als iCalendar (RFC5545)
 * .ics-Datei aus. Optionale Filter werden ueber Query-Parameter uebernommen
 * (gleiche Keys wie in der Manage-View: status, resp, type, loc, highlights).
 *
 * Die generierten VEVENTs sind ganztaegig (DTSTART/DTEND als DATE), da im
 * Event-Modell keine Uhrzeiten gepflegt werden — nur start_date/end_date.
 */
class CalendarIcalController extends Controller
{
    public function download(Request $request)
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        if (!$team) {
            abort(403);
        }

        $query = Event::query()
            ->where('team_id', $team->id)
            ->whereNotNull('start_date');

        if (($s = trim((string) $request->query('status'))) !== '' && $s !== 'Alle') {
            $query->where('status', $s);
        }
        if (($r = trim((string) $request->query('resp'))) !== '') {
            $query->where('responsible', $r);
        }
        if (($t = trim((string) $request->query('type'))) !== '') {
            $query->where('event_type', $t);
        }
        if (($l = trim((string) $request->query('loc'))) !== '') {
            $query->where('location', $l);
        }
        if ($request->query('highlights')) {
            $query->where('is_highlight', true);
        }

        $events = $query->orderBy('start_date')->get();

        $hostname = $request->getHost() ?: 'platforms-events';
        $now = gmdate('Ymd\THis\Z');

        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//BHG//platforms-events//DE';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = 'X-WR-CALNAME:' . $this->ical_escape('Veranstaltungen ' . $team->name);
        $lines[] = 'X-WR-TIMEZONE:Europe/Berlin';

        foreach ($events as $event) {
            $start = $event->start_date;
            // RFC5545: DTEND bei ganztaegigen Events ist EXKLUSIV (= Folgetag).
            $end   = ($event->end_date ?: $event->start_date)->copy()->addDay();

            $summaryParts = [];
            if ($event->event_number) $summaryParts[] = $event->event_number;
            $summaryParts[] = $event->name ?: 'Veranstaltung';

            $descParts = [];
            if ($event->customer)    $descParts[] = 'Kunde: ' . $event->customer;
            if ($event->event_type)  $descParts[] = 'Typ: ' . $event->event_type;
            if ($event->status)      $descParts[] = 'Status: ' . $event->status;
            if ($event->responsible) $descParts[] = 'Verantwortlich: ' . $event->responsible;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:event-' . $event->id . '@' . $hostname;
            $lines[] = 'DTSTAMP:' . $now;
            $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
            $lines[] = 'SUMMARY:' . $this->ical_escape(implode(' · ', $summaryParts));
            if (!empty($descParts)) {
                $lines[] = 'DESCRIPTION:' . $this->ical_escape(implode("\n", $descParts));
            }
            if ($event->location) {
                $lines[] = 'LOCATION:' . $this->ical_escape($event->location);
            }
            if ($event->slug) {
                $lines[] = 'URL:' . route('events.show', ['slug' => $event->slug]);
            }
            if ($event->status === 'Storno') {
                $lines[] = 'STATUS:CANCELLED';
            } elseif (in_array($event->status, ['Definitiv', 'Vertrag', 'Abgeschlossen'], true)) {
                $lines[] = 'STATUS:CONFIRMED';
            } else {
                $lines[] = 'STATUS:TENTATIVE';
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // RFC5545 verlangt CRLF-Zeilenenden.
        $body = implode("\r\n", $lines) . "\r\n";

        $filename = 'veranstaltungen-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($team->name ?? 'team')) . '.ics';

        return response($body, 200, [
            'Content-Type'        => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Escaped Sonderzeichen gemaess RFC5545: Backslash, Semikolon, Komma, Newline.
     */
    private function ical_escape(string $value): string
    {
        $value = str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $value);
        return $value;
    }
}
