<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\NormalizesMwst;
use Platform\Events\Tools\Concerns\RecalculatesOrderItem;

/**
 * Massen-Anlage mehrerer Bestell-Positionen in einem einzigen Call — fuer
 * grosse Imports analog zu BulkCreateQuotePositionsTool.
 *
 * Unterschiede zur Quote-Variante:
 *   - kein `preis` (VK) und kein `basis_ek` (Bestellungen kennen die nicht).
 *   - `gesamt` = `anz × ek` (statt anz × preis).
 *   - kein `beverage_mode`.
 *   - Aggregat `einkauf` statt `umsatz` ueber RecalculatesOrderItem.
 */
class BulkCreateOrderPositionsTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use NormalizesMwst;
    use RecalculatesOrderItem;

    protected const FIELD_ALIASES = [
        'tax_rate' => 'mwst',
        'unit'     => 'gebinde',
    ];

    public function getName(): string
    {
        return 'events.order-positions.bulk.CREATE';
    }

    public function getDescription(): string
    {
        return 'POST /events/order-positions/bulk - Massen-Anlage von Bestell-Positionen. '
            . 'SCOPE: order_item_id|order_item_uuid als Default fuer alle Rows, ODER pro Row '
            . 'eigene order_item_id/uuid mitgeben. Jede Position akzeptiert die gleichen Felder '
            . 'wie events.order-positions.CREATE inkl. MwSt-Numeric-Alias (1/3/0 → 19%/7%/0%). '
            . 'gesamt wird automatisch aus anz × ek berechnet, wenn nicht gesetzt. sort_order '
            . 'wird automatisch fortlaufend ab max+1 vergeben. '
            . 'Atomic-Modus: atomic=true (Default) → alle in einer Transaction; atomic=false → '
            . 'pro Row eigene Transaction, Teil-Erfolge moeglich. '
            . 'Hinweis: OrderPositions haben weder basis_ek noch preis (VK) — `price`/`price_net`/`vk` '
            . 'landen in ignored_fields[].';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'order_item_id'   => ['type' => 'integer', 'description' => 'Default-Scope: alle Rows ohne eigene order_item_id landen hier.'],
                'order_item_uuid' => ['type' => 'string',  'description' => 'Alternative zu order_item_id.'],
                'atomic'          => ['type' => 'boolean', 'description' => 'Default true. Bei false: pro Row eigene Transaction.'],
                'positions' => [
                    'type'        => 'array',
                    'description' => 'Liste der anzulegenden Positionen.',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'order_item_id'    => ['type' => 'integer', 'description' => 'Optional: ueberschreibt das Default-Scope-OrderItem fuer diese Row.'],
                            'order_item_uuid'  => ['type' => 'string'],
                            'gruppe'           => ['type' => 'string'],
                            'name'             => ['type' => 'string'],
                            'anz'              => ['type' => 'string'],
                            'anz2'             => ['type' => 'string'],
                            'start_time'       => ['type' => 'string'],
                            'end_time'         => ['type' => 'string'],
                            'gebinde'          => ['type' => 'string'],
                            'inhalt'           => ['type' => 'string'],
                            'ek'               => ['type' => 'number'],
                            'mwst'             => ['description' => 'String ("0%"/"7%"/"19%") oder numerisch (0/1/3/7/19).'],
                            'gesamt'           => ['type' => 'number', 'description' => 'Optional; default = anz × ek.'],
                            'bemerkung'        => ['type' => 'string'],
                            'procurement_type' => ['type' => 'string'],
                            'sort_order'       => ['type' => 'integer'],
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

            $defaultItem = null;
            if (!empty($arguments['order_item_id'])) {
                $defaultItem = OrderItem::find((int) $arguments['order_item_id']);
            } elseif (!empty($arguments['order_item_uuid'])) {
                $defaultItem = OrderItem::where('uuid', $arguments['order_item_uuid'])->first();
            }

            $atomic = array_key_exists('atomic', $arguments) ? (bool) $arguments['atomic'] : true;

            $created          = [];
            $failed           = [];
            $aliasesAggregate = [];
            $touchedItemIds   = [];
            $sortOffsetByItem = [];

            $processRow = function (array $row, int $index) use ($context, $defaultItem, &$created, &$aliasesAggregate, &$touchedItemIds, &$sortOffsetByItem): ?string {
                $item = $defaultItem;
                if (!empty($row['order_item_id'])) {
                    $item = OrderItem::find((int) $row['order_item_id']);
                } elseif (!empty($row['order_item_uuid'])) {
                    $item = OrderItem::where('uuid', $row['order_item_uuid'])->first();
                }
                if (!$item) {
                    return 'Row[' . $index . ']: order_item_id/order_item_uuid fehlt oder OrderItem nicht gefunden.';
                }
                $event = $item->eventDay?->event;
                if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                    return 'Row[' . $index . ']: Kein Zugriff auf den Vorgang.';
                }

                // Field-Aliases.
                foreach (self::FIELD_ALIASES as $alias => $primary) {
                    if (array_key_exists($alias, $row)
                        && (!array_key_exists($primary, $row) || $row[$primary] === null || $row[$primary] === '')
                    ) {
                        $row[$primary] = $row[$alias];
                        $aliasesAggregate[] = "row[{$index}].{$alias}→{$primary}";
                    }
                }
                // MwSt-Numeric-Alias.
                if ($mwstAlias = $this->normalizeMwstField($row, 'mwst')) {
                    $aliasesAggregate[] = "row[{$index}]." . $mwstAlias;
                }

                if (array_key_exists('mwst', $row)
                    && $row['mwst'] !== null && $row['mwst'] !== ''
                    && !in_array($row['mwst'], ['0%', '7%', '19%'], true)
                ) {
                    return 'Row[' . $index . ']: mwst muss "0%" | "7%" | "19%" sein (auch numerisch: 0/1/3/7/19).';
                }

                $anz = (float) ($row['anz'] ?? 0);
                $ek  = (float) ($row['ek']  ?? 0);
                $gesamt = isset($row['gesamt']) && $row['gesamt'] !== ''
                    ? (float) $row['gesamt']
                    : $anz * $ek;

                $itemId = (int) $item->id;
                if (!array_key_exists('sort_order', $row) || $row['sort_order'] === null || $row['sort_order'] === '') {
                    if (!isset($sortOffsetByItem[$itemId])) {
                        $sortOffsetByItem[$itemId] = (int) OrderPosition::where('order_item_id', $itemId)->max('sort_order');
                    }
                    $sortOffsetByItem[$itemId]++;
                    $sortOrder = $sortOffsetByItem[$itemId];
                } else {
                    $sortOrder = (int) $row['sort_order'];
                }

                $procurementType = isset($row['procurement_type']) && trim((string) $row['procurement_type']) !== ''
                    ? trim((string) $row['procurement_type'])
                    : null;

                $position = OrderPosition::create([
                    'team_id'          => $event->team_id,
                    'user_id'          => Auth::id() ?: $context->user->id,
                    'order_item_id'    => $item->id,
                    'gruppe'           => (string) ($row['gruppe']     ?? ''),
                    'name'             => (string) ($row['name']       ?? ''),
                    'anz'              => (string) ($row['anz']        ?? ''),
                    'anz2'             => (string) ($row['anz2']       ?? ''),
                    'start_time'       => (string) ($row['start_time'] ?? ''),
                    'end_time'         => (string) ($row['end_time']   ?? ''),
                    'gebinde'          => (string) ($row['gebinde']    ?? ''),
                    'inhalt'           => (string) ($row['inhalt']     ?? ''),
                    'ek'               => $ek,
                    'mwst'             => (string) ($row['mwst']       ?? '7%'),
                    'gesamt'           => $gesamt,
                    'bemerkung'        => (string) ($row['bemerkung']  ?? ''),
                    'procurement_type' => $procurementType,
                    'sort_order'       => $sortOrder,
                ]);

                $created[] = [
                    'index'         => $index,
                    'id'            => $position->id,
                    'uuid'          => $position->uuid,
                    'order_item_id' => $position->order_item_id,
                    'name'          => $position->name,
                    'gesamt'        => (float) $position->gesamt,
                ];
                $touchedItemIds[$itemId] = true;
                return null;
            };

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

            $recalculated = [];
            foreach (array_keys($touchedItemIds) as $itemId) {
                $item = OrderItem::find($itemId);
                if ($item) {
                    $this->recalcOrderItem($item);
                    $recalculated[] = (int) $itemId;
                }
            }

            return ToolResult::success([
                'created'                  => $created,
                'failed'                   => $failed,
                'created_count'            => count($created),
                'failed_count'             => count($failed),
                'recalculated_order_items' => $recalculated,
                'aliases_applied'          => $aliasesAggregate,
                'atomic'                   => $atomic,
                'message'                  => sprintf(
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
            'tags'          => ['events', 'order', 'position', 'create', 'bulk', 'import'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'moderate',
            'idempotent'    => false,
            'side_effects'  => ['inserts', 'updates'],
        ];
    }
}
