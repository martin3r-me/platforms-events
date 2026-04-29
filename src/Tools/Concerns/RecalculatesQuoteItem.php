<?php

namespace Platform\Events\Tools\Concerns;

use Platform\Events\Models\QuoteItem;
use Platform\Events\Services\SettingsService;

/**
 * Aktualisiert die Aggregat-Felder eines QuoteItems aus seinen Positionen
 * (artikel ohne Bausteine, positionen inkl. Bausteine, umsatz aus Summe).
 * Spiegelt die Logik aus Livewire\Detail\Quotes::recalcItemFromPositions().
 */
trait RecalculatesQuoteItem
{
    protected function recalcQuoteItem(QuoteItem $item): void
    {
        $positions = $item->posList()->get();

        $bausteinNames = collect(SettingsService::bausteine($item->team_id))
            ->map(fn ($b) => mb_strtolower(trim((string) ($b['name'] ?? ''))))
            ->filter()
            ->all();
        $isBaustein = fn ($gruppe) => in_array(mb_strtolower(trim((string) $gruppe)), $bausteinNames, true);

        $item->update([
            'artikel'    => $positions->filter(fn ($p) => !$isBaustein($p->gruppe))->count(),
            'positionen' => $positions->count(),
            'umsatz'     => (float) $positions->sum('gesamt'),
        ]);
    }
}
