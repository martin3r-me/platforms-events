<?php

namespace Platform\Events\Services;

use Illuminate\Support\Collection;

/**
 * Collection-level Aggregation fuer Quote- und Order-Positionen:
 *   - Anzahl Artikel (ohne Bausteine)
 *   - Netto/MwSt/Brutto-Aufteilung pro Steuersatz
 *
 * Bausteine werden anhand des Gruppen-Namens (case-insensitive) erkannt
 * und aus allen Summen rausgefiltert. Das Sum-Feld (default `gesamt`) ist
 * je nach Preis-Modus des Vorgangs Netto- oder Brutto-Betrag; netto/brutto
 * werden daraus abgeleitet.
 */
class PositionAggregator
{
    /**
     * @param  Collection  $positions   Quote- oder Order-Positionen
     * @param  array       $bausteine   Liste aus Settings (Items mit ['name','bg','text'])
     * @param  string      $priceMode   'netto' (Default) oder 'brutto'
     * @param  string      $sumField    Feld auf dem Modell, das aufsummiert wird
     * @return array{
     *     total_articles: int,
     *     total_net: float,
     *     total_tax: float,
     *     total_gross: float,
     *     mwst_breakdown: \Illuminate\Support\Collection,
     *     is_brutto: bool,
     * }
     */
    public static function aggregate(
        Collection $positions,
        array $bausteine = [],
        string $priceMode = 'netto',
        string $sumField = 'gesamt',
    ): array {
        $bausteinMap = [];
        foreach ($bausteine as $b) {
            $bausteinMap[mb_strtolower(trim((string) ($b['name'] ?? '')))] = $b;
        }
        $isBaustein = static fn (string $g): bool => isset($bausteinMap[mb_strtolower(trim($g))]);

        $articles = $positions->filter(fn ($p) => !$isBaustein((string) $p->gruppe));
        $isBrutto = $priceMode === 'brutto';

        $breakdown = $articles
            ->groupBy(fn ($p) => (string) ($p->mwst ?: '0%'))
            ->map(function ($group, $rate) use ($isBrutto, $sumField) {
                $raw = (float) $group->sum($sumField);
                $pct = (float) filter_var($rate, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if ($isBrutto) {
                    $gross = $raw;
                    $net   = $pct > 0 ? round($gross / (1 + $pct / 100), 2) : $gross;
                    $tax   = round($gross - $net, 2);
                } else {
                    $net   = $raw;
                    $tax   = round($net * ($pct / 100), 2);
                    $gross = round($net + $tax, 2);
                }
                return ['rate' => $rate, 'pct' => $pct, 'net' => $net, 'tax' => $tax, 'gross' => $gross];
            })
            ->sortBy('pct')
            ->values();

        return [
            'total_articles' => $articles->count(),
            'total_net'      => (float) $breakdown->sum('net'),
            'total_tax'      => (float) $breakdown->sum('tax'),
            'total_gross'    => (float) $breakdown->sum('gross'),
            'mwst_breakdown' => $breakdown,
            'is_brutto'      => $isBrutto,
        ];
    }

    /**
     * Pruefen, ob eine einzelne Gruppe ein Baustein ist.
     */
    public static function isBaustein(string $gruppe, array $bausteine): bool
    {
        foreach ($bausteine as $b) {
            if (mb_strtolower(trim((string) ($b['name'] ?? ''))) === mb_strtolower(trim($gruppe))) {
                return true;
            }
        }
        return false;
    }
}
