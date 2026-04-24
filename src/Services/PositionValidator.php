<?php

namespace Platform\Events\Services;

/**
 * Zentrale Validierung fuer Quote-/Order-Positionen beim Anlegen.
 * Jede Regel liefert entweder null (OK) oder eine deutsche Fehlermeldung
 * zum Flashen an die UI (session()->flash('positionError', ...)).
 *
 * Methoden sind statisch, weil die Regeln zustandslos sind und so
 * problemlos auch aus Services (z.B. ArticlePackageApplicator) aufgerufen
 * werden koennen.
 */
class PositionValidator
{
    /**
     * Volle Validierung fuer eine neue Position. Gibt die erste gefundene
     * Fehlermeldung zurueck, sonst null.
     */
    public static function validate(array $position): ?string
    {
        $uhrzeit = (string) ($position['uhrzeit'] ?? '');
        $bis     = (string) ($position['bis'] ?? '');

        if ($uhrzeit !== '' && !PositionCalculator::isValidTime($uhrzeit)) {
            return 'Uhrzeit "' . $uhrzeit . '" ist nicht zulaessig.';
        }
        if ($bis !== '' && !PositionCalculator::isValidTime($bis)) {
            return 'Uhrzeit "' . $bis . '" ist nicht zulaessig.';
        }

        if (self::requiresGruppe($position) && trim((string) ($position['gruppe'] ?? '')) === '') {
            return 'Bitte eine Gruppe auswählen — ohne Gruppe fehlt das Erlöskonto für die Buchhaltung.';
        }

        return null;
    }

    /**
     * True, wenn die Position Inhalt hat, der eine Erloeskonto-Zuordnung
     * erfordert (Name gesetzt oder Preis/EK > 0). Bausteine bringen ihre
     * eigene Gruppe schon mit und fallen damit ebenfalls unter "requires".
     */
    public static function requiresGruppe(array $position): bool
    {
        $name  = trim((string) ($position['name'] ?? ''));
        $preis = (float) ($position['preis'] ?? 0);
        $ek    = (float) ($position['ek'] ?? 0);

        return $name !== '' || $preis > 0 || $ek > 0;
    }
}
