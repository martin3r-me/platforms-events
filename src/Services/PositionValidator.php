<?php

namespace Platform\Events\Services;

use Platform\Events\Models\ArticleGroup;

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
     *
     * @param array|null $allowedGruppen Liste erlaubter Gruppen-Namen. Wenn
     *   gesetzt, muss die Gruppe darin enthalten sein (Schutz vor frei
     *   getippten Gruppen ohne Erloeskonto).
     */
    public static function validate(array $position, ?array $allowedGruppen = null): ?string
    {
        $uhrzeit = (string) ($position['uhrzeit'] ?? '');
        $bis     = (string) ($position['bis'] ?? '');

        if ($uhrzeit !== '' && !PositionCalculator::isValidTime($uhrzeit)) {
            return 'Uhrzeit "' . $uhrzeit . '" ist nicht zulaessig.';
        }
        if ($bis !== '' && !PositionCalculator::isValidTime($bis)) {
            return 'Uhrzeit "' . $bis . '" ist nicht zulaessig.';
        }

        if (!self::requiresGruppe($position)) {
            return null;
        }

        $gruppe = trim((string) ($position['gruppe'] ?? ''));
        if ($gruppe === '') {
            return 'Bitte eine Gruppe auswählen — ohne Gruppe fehlt das Erlöskonto für die Buchhaltung.';
        }

        if ($allowedGruppen !== null && !in_array($gruppe, $allowedGruppen, true)) {
            return 'Gruppe "' . $gruppe . '" existiert nicht im Artikelstamm. Bitte aus der Vorschlagsliste wählen oder unter Artikel → Gruppen anlegen.';
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

    /**
     * Alle fuer ein Team zugelassenen Gruppen-Namen: aktive ArticleGroups
     * (mit Erloeskonten) + frei konfigurierte Bausteine (Text-Zeilen).
     *
     * @return array<string>
     */
    public static function allowedGruppen(?int $teamId): array
    {
        $articleGroups = ArticleGroup::where('team_id', $teamId)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->pluck('name')
            ->all();

        $bausteine = array_map(
            fn ($b) => (string) ($b['name'] ?? ''),
            SettingsService::bausteine($teamId)
        );

        return array_values(array_unique(array_filter(array_merge($articleGroups, $bausteine))));
    }
}
