<?php

namespace Platform\Events\Services;

use Illuminate\Support\Facades\Auth;
use Platform\Events\Models\Event;
use Platform\Events\Models\EventNote;

class ProjektFunctionData
{
    /**
     * Baut das Daten-Array fuer das Projekt-Function-PDF (Kitchen/Manager).
     */
    public static function build(Event $event): array
    {
        $event->loadMissing(['days.quoteItems.posList', 'scheduleItems', 'notes']);

        $dayNames = [
            'Mo' => 'Montag', 'Di' => 'Dienstag', 'Mi' => 'Mittwoch',
            'Do' => 'Donnerstag', 'Fr' => 'Freitag', 'Sa' => 'Samstag', 'So' => 'Sonntag',
        ];

        $eventSlug = str_replace('#', '', (string) ($event->event_number ?? ''));

        $schedule = $event->scheduleItems
            ->sortBy(['datum', 'von'])
            ->map(function ($s) {
                $raw = $s->datum ?? '';
                $short = '';
                $full  = '';
                if ($raw) {
                    try {
                        $c = \Carbon\Carbon::parse($raw);
                        $short = $c->format('d.m.');
                        $full  = $c->format('d.m.Y');
                    } catch (\Throwable $e) {
                        $short = (string) $raw;
                        $full  = (string) $raw;
                    }
                }
                return [
                    'datum'        => $short,
                    'datum_full'   => $full,
                    'datum_raw'    => (string) $raw,
                    'von'          => $s->von ?? '',
                    'bis'          => $s->bis ?? '',
                    'beschreibung' => $s->beschreibung ?? '',
                    'raum'         => $s->raum ?? '',
                    'bemerkung'    => $s->bemerkung ?? '',
                ];
            })
            ->values()
            ->toArray();

        $liefertext = EventNote::where('event_id', $event->id)
            ->where('type', 'liefertext')
            ->pluck('text')
            ->implode("\n");

        $vereinbarungen = EventNote::where('event_id', $event->id)
            ->where('type', 'vereinbarung')
            ->pluck('text')
            ->implode("\n");

        $days = $event->days->map(function ($day) use ($schedule, $dayNames) {
            $datumFormatted = $day->datum
                ? \Carbon\Carbon::parse($day->datum)->format('d.m.Y')
                : '';
            $dayOfWeek = $day->day_of_week ?? '';
            $fullDayName = $dayNames[$dayOfWeek] ?? $dayOfWeek;

            $dayYmd = $day->datum?->format('Y-m-d');
            $daySchedule = collect($schedule)
                ->filter(fn ($s) => ($s['datum_full'] ?? '') === $datumFormatted || ($s['datum_raw'] ?? '') === $dayYmd)
                ->values()
                ->toArray();

            $vorgaenge = ($day->quoteItems ?? collect())
                ->sortBy('sort_order')
                ->map(function ($qi) {
                    $positionen = $qi->posList
                        ->sortBy('sort_order')
                        ->map(fn ($p) => [
                            'gruppe'    => $p->gruppe ?? '',
                            'name'      => $p->name ?? '',
                            'anz'       => $p->anz ?? '',
                            'anz2'      => $p->anz2 ?? '',
                            'uhrzeit'   => $p->uhrzeit ?? '',
                            'bis'       => $p->bis ?? '',
                            'inhalt'    => $p->inhalt ?? '',
                            'gebinde'   => $p->gebinde ?? '',
                            'ek'        => $p->ek ?? '',
                            'preis'     => $p->preis ?? '',
                            'gesamt'    => $p->gesamt ?? '',
                            'mwst'      => $p->mwst ?? '',
                            'bemerkung' => $p->bemerkung ?? '',
                        ])
                        ->values()
                        ->toArray();

                    return [
                        'typ'        => $qi->typ,
                        'positionen' => $positionen,
                    ];
                })
                ->values()
                ->toArray();

            return [
                'datum'       => $datumFormatted,
                'datum_raw'   => $day->datum,
                'day_of_week' => $dayOfWeek,
                'full_day'    => $fullDayName,
                'von'         => $day->von ?? '',
                'bis'         => $day->bis ?? '',
                'pers_von'    => $day->pers_von ?? '',
                'pers_bis'    => $day->pers_bis ?? '',
                'schedule'    => $daySchedule,
                'vorgaenge'   => $vorgaenge,
            ];
        })->values()->toArray();

        $user = Auth::user();

        return [
            'event' => [
                'name'                     => $event->name ?? '',
                'event_number'             => $event->event_number ?? '',
                'event_slug'               => $eventSlug,
                'status'                   => $event->status ?? '',
                'customer'                 => $event->customer ?? '',
                'location'                 => $event->location ?? '',
                'event_type'               => $event->event_type ?? '',
                'responsible'              => $event->responsible ?? '',
                'organizer_contact'        => $event->organizer_contact ?? '',
                'organizer_contact_onsite' => $event->organizer_contact_onsite ?? '',
                'cost_center'              => $event->cost_center ?? '',
                'cost_carrier'             => $event->cost_carrier ?: ($event->event_number ?? ''),
                'delivery_supplier'        => $event->delivery_supplier ?? '',
                'delivery_contact'         => $event->delivery_contact ?? '',
            ],
            'liefertext'     => $liefertext,
            'vereinbarungen' => $vereinbarungen,
            'schedule'       => $schedule,
            'days'           => $days,
            'generated_at'   => now()->format('d.m.Y H:i'),
            'generated_by'   => $user?->name ?? 'System',
        ];
    }
}
