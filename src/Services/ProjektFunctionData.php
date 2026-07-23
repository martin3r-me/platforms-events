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

        // Beschaffungs-Klassifizierung: die Projekt-Function ist das Kuechen-/
        // Regieblatt. Extern beschaffte (supplier) und reine Lager-Positionen
        // (stock) gehoeren nicht darauf; kitchen + Unklassifizierte bleiben.
        $teamId = (int) $event->team_id;
        $articleLookup = [];
        try {
            if (app()->bound(\Platform\Core\Contracts\CatalogArticleProcurementMapProviderInterface::class)) {
                $articleLookup = ProcurementTypeResolver::buildArticleLookup($teamId);
            }
        } catch (\Throwable $e) {
            // Katalog nicht verfuegbar -> keine Klassifizierung, alles bleibt sichtbar
        }

        $dayNames = [
            'Mo' => 'Montag', 'Di' => 'Dienstag', 'Mi' => 'Mittwoch',
            'Do' => 'Donnerstag', 'Fr' => 'Freitag', 'Sa' => 'Samstag', 'So' => 'Sonntag',
        ];

        $eventSlug = str_replace('#', '', (string) ($event->event_number ?? ''));

        $schedule = $event->scheduleItems
            ->sortBy(['datum', 'start_time'])
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
                    'start_time'          => $s->start_time ?? '',
                    'end_time'          => $s->bis ?? '',
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

        $days = $event->days->map(function ($day) use ($schedule, $dayNames, $articleLookup, $teamId) {
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
                ->map(function ($qi) use ($articleLookup, $teamId) {
                    $positionen = $qi->posList
                        ->sortBy('sort_order')
                        ->map(function ($p) use ($articleLookup, $teamId) {
                            $type = ProcurementTypeResolver::resolve(
                                $p->procurement_type,
                                (string) ($p->name ?? ''),
                                $teamId,
                                $articleLookup
                            );
                            return [
                                'gruppe'    => $p->gruppe ?? '',
                                'name'      => $p->name ?? '',
                                'anz'       => $p->anz ?? '',
                                'anz2'      => $p->anz2 ?? '',
                                'start_time'   => $p->uhrzeit ?? '',
                                'end_time'       => $p->bis ?? '',
                                'inhalt'    => $p->inhalt ?? '',
                                'gebinde'   => $p->gebinde ?? '',
                                'ek'        => $p->ek ?? '',
                                'preis'     => $p->preis ?? '',
                                'gesamt'    => $p->gesamt ?? '',
                                'mwst'      => $p->mwst ?? '',
                                'bemerkung' => $p->bemerkung ?? '',
                                'procurement_type' => $type,
                            ];
                        })
                        // extern (supplier) + Lager (stock) raus; kitchen + unklassifiziert bleiben
                        ->reject(fn ($row) => in_array($row['procurement_type'], ['supplier', 'stock'], true))
                        ->values()
                        ->toArray();

                    return [
                        'typ'        => $qi->typ,
                        'positionen' => $positionen,
                    ];
                })
                // Vorgaenge ohne verbleibende Positionen nicht anzeigen
                ->filter(fn ($v) => !empty($v['positionen']))
                ->values()
                ->toArray();

            return [
                'datum'       => $datumFormatted,
                'datum_raw'   => $day->datum,
                'day_of_week' => $dayOfWeek,
                'full_day'    => $fullDayName,
                'start_time'         => $day->start_time ?? '',
                'end_time'         => $day->bis ?? '',
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
                'delivery_address'         => $event->delivery_address ?? '',
                'delivery_location'        => $event->deliveryLocation?->name ?? '',
                'delivery_note'            => $event->delivery_note ?? '',
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
