<?php

namespace Platform\Events\Tools\Concerns;

/**
 * Normalisiert Zeitfeld-Aliases auf das modul-weit einheitliche
 * Schema `start_time` / `end_time`. Historische Konventionen werden
 * weiter als Aliases akzeptiert:
 *
 *   - von / bis           (frueher EventDay, ScheduleItem)
 *   - beginn / ende       (frueher Booking)
 *   - uhrzeit / bis       (frueher Quote-/OrderPosition)
 *   - start / end         (englisch, generisch)
 *
 * Personenzahl-Aliases:
 *   - pers (Bookings)
 *   - pers_von / pers_bis (Days)
 *   - pax / persons (Bonus)
 *
 * Die Methode normalizeTimeFields() arbeitet in-place auf $arguments und
 * liefert die Liste der angewandten Aliases zurueck (fuer aliases_applied).
 */
trait NormalizesTimeFields
{
    /**
     * @param array<string, mixed>          $arguments
     * @param array{start: string, end: string, pers?: string} $primary
     *        primaere Feldnamen, auf die gemappt wird (z.B. ['start' => 'start_time', 'end' => 'end_time', 'pers' => 'pers'])
     * @return array<int, string> Liste der angewandten Aliases (z.B. "von→beginn")
     */
    protected function normalizeTimeFields(array &$arguments, array $primary): array
    {
        $applied = [];

        $startKey = $primary['start'];
        $endKey   = $primary['end'];
        $persKey  = $primary['pers'] ?? null;

        // Start-Zeit: erstes nicht-leeres Alias gewinnt, falls primary noch nicht gesetzt.
        $startAliases = ['start_time', 'beginn', 'von', 'uhrzeit', 'start'];
        if (!isset($arguments[$startKey]) || $arguments[$startKey] === '' || $arguments[$startKey] === null) {
            foreach ($startAliases as $alias) {
                if ($alias === $startKey) continue;
                if (!empty($arguments[$alias])) {
                    $arguments[$startKey] = $arguments[$alias];
                    $applied[] = "{$alias}→{$startKey}";
                    break;
                }
            }
        }

        // End-Zeit
        $endAliases = ['end_time', 'ende', 'bis', 'end'];
        if (!isset($arguments[$endKey]) || $arguments[$endKey] === '' || $arguments[$endKey] === null) {
            foreach ($endAliases as $alias) {
                if ($alias === $endKey) continue;
                if (!empty($arguments[$alias])) {
                    $arguments[$endKey] = $arguments[$alias];
                    $applied[] = "{$alias}→{$endKey}";
                    break;
                }
            }
        }

        // Personenzahl (nur wenn ein primary-Pers-Feld definiert ist)
        if ($persKey !== null && (!isset($arguments[$persKey]) || $arguments[$persKey] === '' || $arguments[$persKey] === null)) {
            foreach (['pers', 'pers_von', 'pers_bis', 'pax', 'persons'] as $alias) {
                if ($alias === $persKey) continue;
                if (!empty($arguments[$alias])) {
                    $arguments[$persKey] = $arguments[$alias];
                    $applied[] = "{$alias}→{$persKey}";
                    break;
                }
            }
        }

        return $applied;
    }

    /**
     * Liefert die kanonische Liste aller bekannten Time/Pers-Aliase – nuetzlich
     * fuer ignored_fields-Diagnose.
     *
     * @return array<int, string>
     */
    protected function timeFieldAliases(): array
    {
        return ['von', 'bis', 'beginn', 'ende', 'uhrzeit', 'start_time', 'end_time', 'start', 'end', 'pers', 'pers_von', 'pers_bis', 'pax', 'persons'];
    }
}
