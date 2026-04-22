<?php

namespace Platform\Events\Services;

use Carbon\Carbon;

/**
 * Gemeinsame Berechnungs-Helpers fuer QuotePosition und OrderPosition:
 *  - Stunden-Differenz zwischen zwei HH:MM-Strings
 *  - Anz.2 aus Zeit-Differenz
 *  - Gesamt aus Anz x (Anz.2 oder 1) x Preis
 *  - Preis rueckwaerts aus Gesamt
 *
 * Keine Datenbank-Abhaengigkeiten; reine Utility-Klasse.
 */
class PositionCalculator
{
    /**
     * Prueft, ob ein String ein valides HH:MM-Format hat (00:00 bis 23:59).
     */
    public static function isValidTime(?string $s): bool
    {
        if ($s === null) return false;
        $s = trim($s);
        if ($s === '') return false;
        return (bool) preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $s);
    }

    /**
     * Liefert den Wert, wenn er ein valides HH:MM ist, sonst leer-String.
     */
    public static function sanitizeTime(?string $s): string
    {
        return self::isValidTime($s) ? trim((string) $s) : '';
    }

    /**
     * Differenz in Stunden zwischen zwei HH:MM-Zeiten. Overnight erkannt.
     * Gibt null zurueck, wenn das Format ungueltig ist.
     */
    public static function hoursDiff(string $von, string $bis): ?float
    {
        try {
            $s = Carbon::createFromFormat('H:i', $von);
            $e = Carbon::createFromFormat('H:i', $bis);
            if ($e->lessThan($s)) $e->addDay();
            $minutes = abs($s->diffInMinutes($e));
            return round($minutes / 60.0, 2);
        } catch (\Throwable $ex) {
            return null;
        }
    }

    /**
     * Liefert Anz.2 als int oder float (2 Nachkommastellen) aus Zeit-Diff,
     * oder null, wenn Zeiten leer/unguelig sind.
     */
    public static function anz2FromTime(?string $von, ?string $bis): ?float
    {
        $von = trim((string) $von);
        $bis = trim((string) $bis);
        if ($von === '' || $bis === '') return null;
        return self::hoursDiff($von, $bis);
    }

    /**
     * Gesamt = anz x (anz2 oder 1) x preis
     * Gibt null zurueck, wenn anz oder preis <= 0 ist.
     */
    public static function gesamt(float $anz, ?float $anz2, float $preis): ?float
    {
        if ($anz <= 0 || $preis <= 0) return null;
        $mult = ($anz2 !== null && $anz2 > 0) ? $anz * $anz2 : $anz;
        return round($mult * $preis, 2);
    }

    /**
     * Preis rueckwaerts aus Gesamt: gesamt / (anz x (anz2 oder 1))
     * Gibt null zurueck, wenn Teiler 0 ist.
     */
    public static function preisFromGesamt(float $anz, ?float $anz2, float $gesamt): ?float
    {
        if ($gesamt <= 0 || $anz <= 0) return null;
        $mult = ($anz2 !== null && $anz2 > 0) ? $anz * $anz2 : $anz;
        if ($mult <= 0) return null;
        return round($gesamt / $mult, 2);
    }

    /**
     * Wendet die Auto-Berechnungen auf eine Positions-Array-Form an.
     *
     * @param array  $position       - mit Schluesseln anz/anz2/uhrzeit/bis/preis|ek/gesamt
     * @param string $changedField   - welches Feld zuletzt geaendert wurde
     * @param string $priceField     - 'preis' (Quote) oder 'ek' (Order)
     * @return array                 - modifizierte Positions-Array-Form
     */
    public static function apply(array $position, string $changedField, string $priceField = 'preis'): array
    {
        // Ungueltige HH:MM-Werte verwerfen, bevor weitergerechnet wird
        if (in_array($changedField, ['uhrzeit', 'bis'], true) && isset($position[$changedField])) {
            $v = (string) $position[$changedField];
            if ($v !== '' && !self::isValidTime($v)) {
                $position[$changedField] = '';
            }
        }

        // Zeit -> Anz.2
        if (in_array($changedField, ['uhrzeit', 'bis'], true)) {
            $h = self::anz2FromTime($position['uhrzeit'] ?? null, $position['bis'] ?? null);
            if ($h !== null) {
                $position['anz2'] = (string) (fmod($h, 1) == 0 ? (int) $h : round($h, 2));
            }
        }

        // Gesamt = Anz x (Anz.2 oder 1) x Preis
        if ($changedField !== 'gesamt' && in_array($changedField, ['anz','anz2','uhrzeit','bis',$priceField], true)) {
            $g = self::gesamt(
                (float) ($position['anz'] ?? 0),
                isset($position['anz2']) && $position['anz2'] !== '' ? (float) $position['anz2'] : null,
                (float) ($position[$priceField] ?? 0)
            );
            if ($g !== null) $position['gesamt'] = $g;
        }

        // Gesamt -> Preis rueckwaerts
        if ($changedField === 'gesamt') {
            $p = self::preisFromGesamt(
                (float) ($position['anz'] ?? 0),
                isset($position['anz2']) && $position['anz2'] !== '' ? (float) $position['anz2'] : null,
                (float) ($position['gesamt'] ?? 0)
            );
            if ($p !== null) $position[$priceField] = $p;
        }

        return $position;
    }
}
