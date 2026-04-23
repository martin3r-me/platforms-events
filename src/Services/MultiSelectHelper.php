<?php

namespace Platform\Events\Services;

/**
 * Zustandslose Array-Helfer fuer Mehrfach-Auswahl in Listen.
 *
 * Die Methoden nehmen den aktuellen Auswahl-Zustand als Array entgegen und
 * liefern den neuen Zustand zurueck - die aufrufende Livewire-Komponente
 * speichert das Ergebnis in ihrer Property.
 */
class MultiSelectHelper
{
    /**
     * Toggelt eine einzelne UUID in der Auswahl.
     */
    public static function toggleSingle(array $selected, string $uuid): array
    {
        if (in_array($uuid, $selected, true)) {
            return array_values(array_diff($selected, [$uuid]));
        }
        $selected[] = $uuid;
        return array_values(array_unique($selected));
    }

    /**
     * Waehlt den Bereich zwischen zwei Indizes aus (oder ab).
     *
     * @param array  $selected      Aktueller Auswahl-Zustand
     * @param array  $orderedUuids  Alle UUIDs in Anzeige-Reihenfolge
     * @param int    $from          Index der zuvor geklickten Zeile
     * @param int    $to            Index der aktuell geklickten Zeile
     * @param bool   $select        true = auswaehlen, false = abwaehlen
     */
    public static function toggleRange(array $selected, array $orderedUuids, int $from, int $to, bool $select): array
    {
        if (empty($orderedUuids)) return $selected;

        $max = count($orderedUuids) - 1;
        $start = max(0, min($from, $to));
        $end   = min($max, max($from, $to));
        $range = array_slice($orderedUuids, $start, $end - $start + 1);

        if ($select) {
            return array_values(array_unique(array_merge($selected, $range)));
        }
        return array_values(array_diff($selected, $range));
    }

    /**
     * Toggle-All: wenn schon alles gewaehlt → leer, sonst alle.
     */
    public static function toggleAll(array $selected, array $allUuids): array
    {
        return count($selected) === count($allUuids) ? [] : array_values($allUuids);
    }

    /**
     * Entfernt die gegebenen UUIDs aus der Auswahl (z.B. nach Loeschen).
     */
    public static function remove(array $selected, array $uuids): array
    {
        return array_values(array_diff($selected, $uuids));
    }
}
