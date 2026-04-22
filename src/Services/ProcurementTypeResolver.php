<?php

namespace Platform\Events\Services;

use Platform\Events\Models\Article;

/**
 * Ermittelt den effektiven Beschaffungs-Typ einer Position:
 *  1. Position-Override (procurement_type auf der Position selbst)
 *  2. Artikel aus Stamm (per Name-Match, team-gefiltert)
 *  3. null  = unklassifiziert
 */
class ProcurementTypeResolver
{
    public const ALLOWED = ['stock', 'supplier', 'kitchen'];

    /**
     * Effektiver Typ oder null, wenn weder Override noch Artikel-Match.
     * $positionType: direkter Wert aus position.procurement_type (nullable).
     * $positionName: der Positions-Name, fuer Artikel-Lookup per Name.
     */
    public static function resolve(?string $positionType, string $positionName, int $teamId, ?array $articleLookup = null): ?string
    {
        $t = $positionType ? trim((string) $positionType) : null;
        if ($t && in_array($t, self::ALLOWED, true)) {
            return $t;
        }

        $key = mb_strtolower(trim($positionName));
        if ($key === '') return null;

        if ($articleLookup === null) {
            $articleLookup = self::buildArticleLookup($teamId);
        }

        return $articleLookup[$key] ?? null;
    }

    /**
     * Baut eine Name(lower) -> procurement_type-Map fuer ein Team auf.
     * Zum wiederholten Aufloesen in Schleifen weitergereicht, um N+1 zu vermeiden.
     */
    public static function buildArticleLookup(int $teamId): array
    {
        return Article::where('team_id', $teamId)
            ->select('name', 'procurement_type')
            ->get()
            ->mapWithKeys(fn ($a) => [
                mb_strtolower(trim((string) $a->name)) => $a->procurement_type ?: 'stock',
            ])
            ->all();
    }

    /**
     * Labels fuer die UI (Pro-Position-Dropdown).
     */
    public static function labels(): array
    {
        return [
            'stock'    => 'Lager',
            'supplier' => 'Extern',
            'kitchen'  => 'Küche',
        ];
    }
}
