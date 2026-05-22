<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\RecalculatesOrderItem;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Massen-Soft-Delete mehrerer Bestell-Positionen. Scope + Filter analog
 * zu BulkUpdateOrderPositionsTool. Recalc aller betroffenen OrderItems
 * erfolgt einmal am Ende.
 */
class BulkDeleteOrderPositionsTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use RecalculatesOrderItem;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.order-positions.bulk.DELETE';
    }

    public function getDescription(): string
    {
        return 'POST /events/order-positions/bulk/delete - Massen-Soft-Delete von Bestell-Positionen. '
            . 'SCOPE (genau einer): '
            . '(1) order_item_id|order_item_uuid – nur Positionen dieses Vorgangs. '
            . '(2) event_id|event_uuid|event_number – alle Positionen aller Order-Items aller Tage des Events. '
            . 'INNERHALB des Scopes mind. ein Filter ODER confirm_scope_wide=true: '
            . 'position_ids[], gruppe (exakt), gruppe_contains, name_contains. '
            . 'Atomic-Modus: atomic=true (Default) → alle Deletes in einer Transaction; '
            . 'atomic=false → pro Position eigene Transaction, Teil-Erfolge moeglich. '
            . 'Recalc aller betroffenen OrderItems am Ende automatisch (einmal pro Item).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'order_item_id'      => ['type' => 'integer', 'description' => 'Scope: nur Positionen dieses Vorgangs.'],
                'order_item_uuid'    => ['type' => 'string'],
                'position_ids'       => ['type' => 'array', 'items' => ['type' => 'integer']],
                'gruppe'             => ['type' => 'string', 'description' => 'Filter: exakte Gruppe.'],
                'gruppe_contains'    => ['type' => 'string', 'description' => 'Filter: Substring in gruppe (case-insensitive).'],
                'name_contains'      => ['type' => 'string', 'description' => 'Filter: Substring in name (case-insensitive).'],
                'confirm_scope_wide' => ['type' => 'boolean', 'description' => 'true = ALLE Positionen im Scope loeschen, wenn kein zusaetzlicher Filter gesetzt ist.'],
                'atomic'             => ['type' => 'boolean', 'description' => 'Default true. Bei false: pro Position eigene Transaction, Teil-Erfolge moeglich.'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            // ----- Scope aufloesen (genau einer) -----
            $scope = null; // 'item' | 'event'
            $scopeRef = null;
            $eventForAccess = null;

            if (!empty($arguments['order_item_id']) || !empty($arguments['order_item_uuid'])) {
                $scope = 'item';
                $scopeRef = !empty($arguments['order_item_id'])
                    ? OrderItem::find((int) $arguments['order_item_id'])
                    : OrderItem::where('uuid', $arguments['order_item_uuid'])->first();
                if (!$scopeRef) {
                    return ToolResult::error('ORDER_ITEM_NOT_FOUND', 'Order-Vorgang nicht gefunden.');
                }
                $eventForAccess = $scopeRef->eventDay?->event;
            } elseif (!empty($arguments['event_id']) || !empty($arguments['event_uuid']) || !empty($arguments['event_number'])) {
                $resolved = $this->resolveEvent($arguments, $context);
                if ($resolved instanceof ToolResult) {
                    return $resolved;
                }
                $scope = 'event';
                $scopeRef = $resolved;
                $eventForAccess = $resolved;
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'Scope erforderlich: order_item_id|order_item_uuid ODER event_id|event_uuid|event_number.');
            }

            if (!$eventForAccess || !$context->user->teams()->where('teams.id', $eventForAccess->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf den Scope.');
            }

            // ----- OrderItem-IDs im Scope sammeln -----
            $itemIds = [];
            if ($scope === 'item') {
                $itemIds = [(int) $scopeRef->id];
            } elseif ($scope === 'event') {
                $itemIds = OrderItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $scopeRef->id))->pluck('id')->all();
            }
            if (empty($itemIds)) {
                return ToolResult::success([
                    'scope'         => $scope,
                    'deleted_count' => 0,
                    'deleted_ids'   => [],
                    'message'       => 'Keine OrderItems im Scope.',
                ]);
            }

            // ----- Filter -----
            $query = OrderPosition::whereIn('order_item_id', $itemIds);
            $hasFilter = false;

            $idList = $arguments['position_ids'] ?? [];
            if (is_array($idList) && !empty($idList)) {
                $ids = array_values(array_unique(array_filter(array_map('intval', $idList))));
                $query->whereIn('id', $ids);
                $hasFilter = true;
            }
            if (!empty($arguments['gruppe'])) {
                $query->where('gruppe', $arguments['gruppe']);
                $hasFilter = true;
            }
            if (!empty($arguments['gruppe_contains'])) {
                $query->where('gruppe', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $arguments['gruppe_contains']) . '%');
                $hasFilter = true;
            }
            if (!empty($arguments['name_contains'])) {
                $query->where('name', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $arguments['name_contains']) . '%');
                $hasFilter = true;
            }
            $confirmWide = (bool) ($arguments['confirm_scope_wide'] ?? false);
            if (!$hasFilter && !$confirmWide) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein Filter angegeben. Setze position_ids[]/gruppe/gruppe_contains/name_contains ODER confirm_scope_wide=true (loescht ALLE Positionen im Scope).');
            }

            $positions = $query->get();
            if ($positions->isEmpty()) {
                return ToolResult::success([
                    'scope'         => $scope,
                    'deleted_count' => 0,
                    'deleted_ids'   => [],
                    'message'       => 'Keine Positionen entsprechen dem Filter.',
                ]);
            }

            $atomic = array_key_exists('atomic', $arguments) ? (bool) $arguments['atomic'] : true;

            $deleted = [];
            $failed = [];
            $touchedItemIds = [];

            $deleteOne = function (OrderPosition $pos) use (&$deleted, &$touchedItemIds) {
                $id = (int) $pos->id;
                $itemId = (int) $pos->order_item_id;
                $pos->delete();
                $deleted[] = $id;
                $touchedItemIds[$itemId] = true;
            };

            if ($atomic) {
                try {
                    DB::transaction(function () use ($positions, $deleteOne) {
                        foreach ($positions as $pos) {
                            $deleteOne($pos);
                        }
                    });
                } catch (\Throwable $e) {
                    return ToolResult::error('BULK_DELETE_FAILED',
                        'Atomic-Modus: erste Fehler-Position hat alle Deletes zurueckgerollt. Detail: ' . $e->getMessage());
                }
            } else {
                foreach ($positions as $pos) {
                    try {
                        DB::transaction(function () use ($pos, $deleteOne) {
                            $deleteOne($pos);
                        });
                    } catch (\Throwable $e) {
                        $failed[] = ['id' => (int) $pos->id, 'error' => $e->getMessage()];
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

            $known = [
                'event_id', 'event_uuid', 'event_number',
                'order_item_id', 'order_item_uuid',
                'position_ids', 'gruppe', 'gruppe_contains', 'name_contains',
                'confirm_scope_wide', 'atomic',
            ];
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            return ToolResult::success([
                'scope'                    => $scope,
                'event_id'                 => $eventForAccess->id,
                'deleted_count'            => count($deleted),
                'deleted_ids'              => $deleted,
                'failed'                   => $failed,
                'failed_count'             => count($failed),
                'recalculated_order_items' => $recalculated,
                'atomic'                   => $atomic,
                'ignored_fields'           => $ignored,
                'message'                  => sprintf(
                    '%d Position(en) im %s-Scope geloescht, %d fehlgeschlagen (Recalc fuer %d Vorgang(e)).',
                    count($deleted), $scope, count($failed), count($recalculated)
                ),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Delete: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'order', 'position', 'delete', 'bulk'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'destructive',
            'idempotent'    => false,
            'side_effects'  => ['deletes', 'updates'],
        ];
    }
}
