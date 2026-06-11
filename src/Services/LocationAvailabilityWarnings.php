<?php

namespace Platform\Events\Services;

use Illuminate\Support\Carbon;

/**
 * Verfuegbarkeits-Warnungen fuer Raumbuchungen — duenne Bruecke zum
 * AvailabilityService des Locations-Moduls. Liefert menschenlesbare
 * Warn-Strings (Sperrzeit, harte Belegung durch andere Events,
 * PAX-Ueberschreitung) und ist bewusst NICHT blockierend: parallele
 * Optionen sind Teil des Optionsgeschaefts.
 *
 * Ohne installiertes Locations-Modul (oder bei Fehlern) kommt eine leere
 * Liste zurueck — Aufrufer muessen nichts guarden.
 */
class LocationAvailabilityWarnings
{
    /**
     * @param int|null    $locationId    Location der Buchung (null => keine Warnungen)
     * @param int|null    $ignoreEventId Buchungen dieses Events ausklammern (Selbst-Kollision)
     * @param array<int, string|null> $dates Buchungstage (YYYY-MM-DD, Freitext wird gekuerzt)
     * @param string|null $pers          Personenzahl-Freitext (z. B. "80-120") fuer den PAX-Check
     * @return array<int, string>
     */
    public function for(?int $locationId, ?int $ignoreEventId, array $dates, ?string $pers = null): array
    {
        $serviceClass = '\\Platform\\Locations\\Services\\AvailabilityService';
        $dates = array_values(array_unique(array_filter(
            array_map(fn ($d) => $d ? substr(trim((string) $d), 0, 10) : null, $dates)
        )));

        if (!$locationId || $dates === [] || !class_exists($serviceClass)) {
            return [];
        }

        try {
            $location = \Platform\Locations\Models\Location::find($locationId);
            if (!$location) {
                return [];
            }

            sort($dates);
            $result = app($serviceClass)->check($location, $dates[0], end($dates), $ignoreEventId);

            $name = $location->kuerzel ?: $location->name;
            $warnings = [];

            foreach ($dates as $date) {
                $day = $result['days'][$date] ?? null;
                if (!$day) {
                    continue;
                }

                try {
                    $dateLabel = Carbon::parse($date)->format('d.m.Y');
                } catch (\Throwable $e) {
                    $dateLabel = $date;
                }

                if ($day['status'] === $serviceClass::STATUS_GESPERRT) {
                    $reasons = collect($day['blockings'])->pluck('reason')->filter()->unique()->implode(', ');
                    $warnings[] = "{$dateLabel}: {$name} ist gesperrt" . ($reasons !== '' ? " — {$reasons}" : '') . '.';
                } elseif ($day['status'] === $serviceClass::STATUS_BELEGT) {
                    $others = collect($day['bookings'])
                        ->filter(fn ($b) => in_array($b['optionsrang'], $serviceClass::HARD_RANKS, true))
                        ->pluck('event')->filter()->unique()->implode(', ');
                    $warnings[] = "{$dateLabel}: {$name} ist bereits definitiv belegt" . ($others !== '' ? " ({$others})" : '') . '.';
                }
            }

            // PAX-Check: groesste Zahl aus dem Freitext (z. B. "80-120") gegen pax_max.
            if ($pers !== null && $location->pax_max && preg_match_all('/\d+/', $pers, $m) && $m[0] !== []) {
                $maxPers = max(array_map('intval', $m[0]));
                if ($maxPers > (int) $location->pax_max) {
                    $warnings[] = "Personenzahl {$maxPers} überschreitet die Kapazität von {$name} (max. {$location->pax_max} PAX).";
                }
            }

            return $warnings;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
