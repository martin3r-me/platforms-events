<?php

namespace Platform\Events\Tools\Concerns;

use Platform\Events\Models\OrderItem;
use Platform\Events\Services\SettingsService;

/**
 * Aktualisiert die Aggregat-Felder eines OrderItems aus seinen Positionen
 * (artikel ohne Bausteine, positionen inkl. Bausteine, einkauf aus Summe).
 * Spiegelt die Logik aus Livewire\Detail\Orders::recalculateItem().
 */
trait RecalculatesOrderItem
{
    protected function recalcOrderItem(OrderItem $item): void
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
            'einkauf'    => (float) $positions->sum('gesamt'),
        ]);
    }
}
