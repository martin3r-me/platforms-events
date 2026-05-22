<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\NormalizesMwst;
use Platform\Events\Tools\Concerns\NormalizesTimeFields;
use Platform\Events\Tools\Concerns\RecalculatesQuoteItem;

/**
 * Massen-Anlage mehrerer Angebots-Positionen in einem einzigen Call —
 * fuer grosse Imports (Excel, LLM-generierte Listen).
 *
 * Scope-Optionen:
 *   - quote_item_id|quote_item_uuid : alle Positionen landen in diesem Vorgang
 *     (sofern nicht explizit pro-Row ueberschrieben).
 *   - keine Scope-Angabe: jede Row muss eigene quote_item_id/quote_item_uuid
 *     mitbringen.
 *
 * Pro Row gilt die gleiche Logik wie events.quote-positions.CREATE:
 *   - MwSt-Numeric-Alias (1→"19%", 3→"7%", 0→"0%") via NormalizesMwst.
 *   - Field-Aliases (price_net/price/vk→preis, tax_rate→mwst, unit→gebinde).
 *   - Auto-`gesamt` aus `anz × preis` wenn nicht explizit gesetzt.
 *   - `sort_order` wird automatisch fortlaufend nach max + 1, +2, …
 *     pro Vorgang vergeben (kann pro Row ueberschrieben werden).
 *
 * Atomic-Modus:
 *   - `atomic=true` (Default): alle Rows in einer DB-Transaction. Bei einem
 *     Fehler in einer Row wird die ganze Transaction zurueckgerollt; nichts
 *     wird gespeichert. `failed[]` enthaelt den Detail-Fehler.
 *   - `atomic=false`: pro Row eigene Transaktion. Erfolgreiche Rows bleiben,
 *     fehlerhafte landen in `failed[]` mit `index` + `error`.
 *
 * Recalc der betroffenen QuoteItems (artikel/positionen/umsatz) erfolgt am
 * Ende einmal pro Item — nicht pro Row.
 */
class BulkCreateQuotePositionsTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use NormalizesMwst;
    use NormalizesTimeFields;
    use RecalculatesQuoteItem;

    /** Aliases analog zu UpdateQuotePositionTool — pro Row anwendbar. */
    protected const FIELD_ALIASES = [
        'price_net' => 'preis',
        'price'     => 'preis',
        'vk'        => 'preis',
        'tax_rate'  => 'mwst',
        'unit'      => 'gebinde',
    ];

    public function getName(): string
    {
        return 'events.quote-positions.bulk.CREATE';
    }

    public function getDescription(): string
    {
        return 'POST /events/quote-positions/bulk - Massen-Anlage von Angebots-Positionen. '
            . 'SCOPE: quote_item_id|quote_item_uuid als Default fuer alle Rows, ODER pro Row '
            . 'eigene quote_item_id/uuid mitgeben. Jede Position akzeptiert die gleichen Felder '
            . 'wie events.quote-positions.CREATE inkl. MwSt-Numeric-Alias (1/3/0 → 19%/7%/0%) und '
            . 'price_net/price/vk-Aliases. gesamt wird automatisch aus anz × preis berechnet, wenn '
            . 'nicht gesetzt. sort_order wird automatisch fortlaufend ab max+1 vergeben. '
            . 'Atomic-Modus: atomic=true (Default) → alle in einer Transaction; atomic=false → '
            . 'pro Row eigene Transaction, Teil-Erfolge moeglich.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'quote_item_id'   => ['type' => 'integer', 'description' => 'Default-Scope: alle Rows ohne eigene quote_item_id landen hier.'],
                'quote_item_uuid' => ['type' => 'string',  'description' => 'Alternative zu quote_item_id.'],
                'atomic'          => ['type' => 'boolean', 'description' => 'Default true. Bei false: pro Row eigene Transaction, Teil-Erfolge moeglich.'],
                'positions' => [
                    'type'        => 'array',
                    'description' => 'Liste der anzulegenden Positionen.',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'quote_item_id'    => ['type' => 'integer', 'description' => 'Optional: ueberschreibt das Default-Scope-QuoteItem fuer diese Row.'],
                            'quote_item_uuid'  => ['type' => 'string'],
                            'gruppe'           => ['type' => 'string', 'description' => 'Gruppe. Entspricht der Wert einem Text-Baustein → Text-Zeile ohne Preis.'],
                            'name'             => ['type' => 'string'],
                            'anz'              => ['type' => 'string'],
                            'anz2'             => ['type' => 'string'],
                            'start_time'       => ['type' => 'string'],
                            'end_time'         => ['type' => 'string'],
                            'gebinde'          => ['type' => 'string'],
                            'inhalt'           => ['type' => 'string'],
                            'ek'               => ['type' => 'number'],
                            'preis'            => ['type' => 'number'],
                            'mwst'             => ['description' => 'String ("0%"/"7%"/"19%") oder numerisch (0/1/3/7/19) — Numeric-Alias wird gemappt.'],
                            'gesamt'           => ['type' => 'number', 'description' => 'Optional; default = anz × preis.'],
                            'bemerkung'        => ['type' => 'string'],
                            'beverage_mode'    => ['type' => 'string'],
                            'procurement_type' => ['type' => 'string'],
                            'sort_order'       => ['type' => 'integer', 'description' => 'Optional; default = fortlaufend ab max+1 pro Vorgang.'],
                        ],
                        'required'   => ['name'],
                    ],
                ],
            ],
            'required' => ['positions'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $positions = is_array($arguments['positions'] ?? null) ? $arguments['positions'] : [];
            if (empty($positions)) {
                return ToolResult::error('VALIDATION_ERROR', 'positions[] darf nicht leer sein.');
            }

            // Default-Scope-QuoteItem aufloesen, falls vorhanden.
            $defaultItem = null;
            if (!empty($arguments['quote_item_id'])) {
                $defaultItem = QuoteItem::find((int) $arguments['quote_item_id']);
            } elseif (!empty($arguments['quote_item_uuid'])) {
                $defaultItem = QuoteItem::where('uuid', $arguments['quote_item_uuid'])->first();
            }

            $atomic = array_key_exists('atomic', $arguments) ? (bool) $arguments['atomic'] : true;

            $created          = [];
            $failed           = [];
            $aliasesAggregate = [];
            $touchedItemIds   = [];
            $sortOffsetByItem = []; // item_id → naechster sort_order

            $processRow = function (array $row, int $index) use ($context, $defaultItem, &$created, &$aliasesAggregate, &$touchedItemIds, &$sortOffsetByItem): ?string {
                // QuoteItem pro Row aufloesen (Row > Default).
                $item = $defaultItem;
                if (!empty($row['quote_item_id'])) {
                    $item = QuoteItem::find((int) $row['quote_item_id']);
                } elseif (!empty($row['quote_item_uuid'])) {
                    $item = QuoteItem::where('uuid', $row['quote_item_uuid'])->first();
                }
                if (!$item) {
                    return 'Row[' . $index . ']: quote_item_id/quote_item_uuid fehlt oder QuoteItem nicht gefunden.';
                }
                $event = $item->eventDay?->event;
                if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                    return 'Row[' . $index . ']: Kein Zugriff auf den Vorgang.';
                }

                // Field-Aliases pro Row mappen.
                foreach (self::FIELD_ALIASES as $alias => $primary) {
                    if (array_key_exists($alias, $row)
                        && (!array_key_exists($primary, $row) || $row[$primary] === null || $row[$primary] === '')
                    ) {
                        $row[$primary] = $row[$alias];
                        $aliasesAggregate[] = "row[{$index}].{$alias}→{$primary}";
                    }
                }
                // Time-Aliase pro Row (uhrzeit/von/beginn → start_time, bis/ende → end_time).
                foreach ($this->normalizeTimeFields($row, ['start' => 'start_time', 'end' => 'end_time']) as $applied) {
                    $aliasesAggregate[] = "row[{$index}].{$applied}";
                }
                // MwSt-Numeric-Alias.
                if ($mwstAlias = $this->normalizeMwstField($row, 'mwst')) {
                    $aliasesAggregate[] = "row[{$index}]." . $mwstAlias;
                }

                // MwSt strict-Validierung.
                if (array_key_exists('mwst', $row)
                    && $row['mwst'] !== null && $row['mwst'] !== ''
                    && !in_array($row['mwst'], ['0%', '7%', '19%'], true)
                ) {
                    return 'Row[' . $index . ']: mwst muss "0%" | "7%" | "19%" sein (auch numerisch: 0/1/3/7/19).';
                }

                $anz   = (float) ($row['anz']   ?? 0);
                $preis = (float) ($row['preis'] ?? 0);
                $gesamt = isset($row['gesamt']) && $row['gesamt'] !== ''
                    ? (float) $row['gesamt']
                    : $anz * $preis;

                // sort_order auto-fortlaufend pro Item.
                $itemId = (int) $item->id;
                if (!array_key_exists('sort_order', $row) || $row['sort_order'] === null || $row['sort_order'] === '') {
                    if (!isset($sortOffsetByItem[$itemId])) {
                        $sortOffsetByItem[$itemId] = (int) QuotePosition::where('quote_item_id', $itemId)->max('sort_order');
                    }
                    $sortOffsetByItem[$itemId]++;
                    $sortOrder = $sortOffsetByItem[$itemId];
                } else {
                    $sortOrder = (int) $row['sort_order'];
                }

                $beverageMode = isset($row['beverage_mode']) && trim((string) $row['beverage_mode']) !== ''
                    ? trim((string) $row['beverage_mode'])
                    : null;
                $procurementType = isset($row['procurement_type']) && trim((string) $row['procurement_type']) !== ''
                    ? trim((string) $row['procurement_type'])
                    : null;

                $position = QuotePosition::create([
                    'team_id'          => $event->team_id,
                    'user_id'          => Auth::id() ?: $context->user->id,
                    'quote_item_id'    => $item->id,
                    'gruppe'           => (string) ($row['gruppe']     ?? ''),
                    'name'             => (string) ($row['name']       ?? ''),
                    'anz'              => (string) ($row['anz']        ?? ''),
                    'anz2'             => (string) ($row['anz2']       ?? ''),
                    'start_time'       => (string) ($row['start_time'] ?? ''),
                    'end_time'         => (string) ($row['end_time']   ?? ''),
                    'gebinde'          => (string) ($row['gebinde']    ?? ''),
                    'inhalt'           => (string) ($row['inhalt']     ?? ''),
                    'ek'               => (float)  ($row['ek']         ?? 0),
                    'preis'            => $preis,
                    'mwst'             => (string) ($row['mwst']       ?? '7%'),
                    'gesamt'           => $gesamt,
                    'bemerkung'        => (string) ($row['bemerkung']  ?? ''),
                    'beverage_mode'    => $beverageMode,
                    'procurement_type' => $procurementType,
                    'sort_order'       => $sortOrder,
                ]);

                $created[] = [
                    'index'         => $index,
                    'id'            => $position->id,
                    'uuid'          => $position->uuid,
                    'quote_item_id' => $position->quote_item_id,
                    'name'          => $position->name,
                    'gesamt'        => (float) $position->gesamt,
                ];
                $touchedItemIds[$itemId] = true;
                return null; // kein Error
            };

            // ---------- Ausfuehrung: atomic vs. non-atomic ----------
            if ($atomic) {
                try {
                    DB::transaction(function () use ($positions, $processRow, &$failed) {
                        foreach ($positions as $index => $row) {
                            if (!is_array($row)) {
                                throw new \RuntimeException('Row[' . $index . ']: Position muss ein Objekt sein.');
                            }
                            $err = $processRow($row, (int) $index);
                            if ($err !== null) {
                                throw new \RuntimeException($err);
                            }
                        }
                    });
                } catch (\Throwable $e) {
                    return ToolResult::error('BULK_CREATE_FAILED',
                        'Atomic-Modus: Erste Fehler-Row hat alle Rows zurueckgerollt. Detail: ' . $e->getMessage());
                }
            } else {
                foreach ($positions as $index => $row) {
                    if (!is_array($row)) {
                        $failed[] = ['index' => (int) $index, 'error' => 'Position muss ein Objekt sein.'];
                        continue;
                    }
                    try {
                        DB::transaction(function () use ($row, $index, $processRow, &$failed) {
                            $err = $processRow($row, (int) $index);
                            if ($err !== null) {
                                $failed[] = ['index' => (int) $index, 'error' => $err];
                            }
                        });
                    } catch (\Throwable $e) {
                        $failed[] = ['index' => (int) $index, 'error' => $e->getMessage()];
                    }
                }
            }

            // Einmaliger Recalc pro betroffenem QuoteItem.
            $recalculated = [];
            foreach (array_keys($touchedItemIds) as $itemId) {
                $item = QuoteItem::find($itemId);
                if ($item) {
                    $this->recalcQuoteItem($item);
                    $recalculated[] = (int) $itemId;
                }
            }

            return ToolResult::success([
                'created'                => $created,
                'failed'                 => $failed,
                'created_count'          => count($created),
                'failed_count'           => count($failed),
                'recalculated_quote_items' => $recalculated,
                'aliases_applied'        => $aliasesAggregate,
                'atomic'                 => $atomic,
                'message'                => sprintf(
                    '%d Position(en) angelegt, %d fehlgeschlagen (Recalc fuer %d Vorgang(e)).',
                    count($created), count($failed), count($recalculated)
                ),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei Bulk-Create: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'mutation',
            'tags'          => ['events', 'quote', 'position', 'create', 'bulk', 'import'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'moderate',
            'idempotent'    => false,
            'side_effects'  => ['inserts', 'updates'],
        ];
    }
}
