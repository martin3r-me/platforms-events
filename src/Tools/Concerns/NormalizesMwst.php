<?php

namespace Platform\Events\Tools\Concerns;

/**
 * Wandelt numerische MwSt-Codes (typisch aus Excel/DATEV/Lexware) auf den
 * kanonischen String "0%" | "7%" | "19%" um. Damit kann das LLM Excel-Werte
 * direkt durchreichen, ohne sie vorher zu uebersetzen.
 *
 * Mapping (DATEV-/Lexware-Standard):
 *   0   → "0%"
 *   1   → "19%"  (Regelsatz)
 *   3   → "7%"   (Ermaessigter Satz)
 *
 * Akzeptiert auch String-Varianten ("19", "7,0", "0.0") und Prozent ohne
 * Suffix ("19" → "19%"). Bereits korrekte Werte ("0%" | "7%" | "19%")
 * bleiben unveraendert.
 */
trait NormalizesMwst
{
    /**
     * Wendet die Normalisierung auf $arguments[$key] an, falls vorhanden.
     * Liefert null wenn nichts geaendert wurde, sonst einen
     * aliases_applied-Eintrag wie `"mwst:1->19%"`.
     */
    protected function normalizeMwstField(array &$arguments, string $key): ?string
    {
        if (!array_key_exists($key, $arguments)) {
            return null;
        }
        $raw = $arguments[$key];
        if ($raw === null || $raw === '') {
            return null;
        }

        $normalized = self::normalizeMwstValue($raw);
        if ($normalized === null) {
            return null; // Unbekannt — Validation am Aufrufer wirft VALIDATION_ERROR.
        }
        if ($normalized === (string) $raw) {
            return null; // Schon kanonisch.
        }

        $arguments[$key] = $normalized;
        return "{$key}:" . (is_string($raw) ? $raw : (string) $raw) . '->' . $normalized;
    }

    /**
     * Pures Mapping ohne Seiteneffekt. Nicht-erkannte Werte → null.
     */
    public static function normalizeMwstValue(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            // Schon kanonisch?
            if (in_array($trimmed, ['0%', '7%', '19%'], true)) {
                return $trimmed;
            }
            // Numerischer String?
            if (is_numeric(str_replace(',', '.', $trimmed))) {
                $num = (float) str_replace(',', '.', $trimmed);
                return self::mapNumericToMwst($num);
            }
            return null;
        }

        if (is_int($raw) || is_float($raw)) {
            return self::mapNumericToMwst((float) $raw);
        }

        return null;
    }

    /**
     * Mapped einen numerischen Wert:
     *   0   → "0%"
     *   1   → "19%"
     *   3   → "7%"
     *   7   → "7%"  (Prozent-Notation ohne Suffix)
     *   19  → "19%" (Prozent-Notation ohne Suffix)
     */
    protected static function mapNumericToMwst(float $num): ?string
    {
        // Ganzzahlige Toleranz (0.0, 1.0, 3.0 etc.).
        if (abs($num - 0) < 0.001)  return '0%';
        if (abs($num - 1) < 0.001)  return '19%';
        if (abs($num - 3) < 0.001)  return '7%';
        if (abs($num - 7) < 0.001)  return '7%';
        if (abs($num - 19) < 0.001) return '19%';
        return null;
    }
}
