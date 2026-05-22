<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\OrderItem;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Massen-Soft-Delete mehrerer Bestell-Vorgaenge (OrderItem). Cascade auf
 * OrderPositions analog zu BulkDeleteQuoteItemsTool.
 */
class BulkDeleteOrderItemsTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.order-items.bulk.DELETE';
    }

    public function getDescription(): string
    {
        return 'POST /events/order-items/bulk/delete - Massen-Soft-Delete von Bestell-Vorgaengen (OrderItem). '
            . 'SCOPE (genau einer): '
            . '(1) order_item_ids[] – explizite Liste von Item-IDs. '
            . '(2) event_id|event_uuid|event_number + confirm_event_wide=true – ALLE OrderItems des Events. '
            . 'Jeder Delete cascadet alle OrderPositions des Vorgangs. '
            . 'Atomic-Modus: atomic=true (Default) → alle Deletes in einer Transaction; '
            . 'atomic=false → pro Item eigene Transaction, Teil-Erfolge moeglich. '
            . 'Verbindungen zu QuoteItems (Convert/Sync) bleiben erhalten.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'order_item_ids'     => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Explizite Liste von OrderItem-IDs.'],
                'confirm_event_wide' => ['type' => 'boolean', 'description' => 'true = ALLE OrderItems des angegebenen Events loeschen. event_* ist dann Pflicht.'],
                'atomic'             => ['type' => 'boolean', 'description' => 'Default true. Bei false: pro Item eigene Transaction.'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $idList = $arguments['order_item_ids'] ?? [];
            $explicitIds = is_array($idList)
                ? array_values(array_unique(array_filter(array_map('intval', $idList))))
                : [];
            $confirmWide = (bool) ($arguments['confirm_event_wide'] ?? false);

            if (empty($explicitIds) && !$confirmWide) {
                return ToolResult::error('VALIDATION_ERROR', 'Scope erforderlich: order_item_ids[] ODER event_*+confirm_event_wide=true.');
            }
            if (!empty($explicitIds) && $confirmWide) {
                return ToolResult::error('VALIDATION_ERROR', 'order_item_ids[] und confirm_event_wide=true gleichzeitig sind nicht erlaubt.');
            }

            $eventForAccess = null;
            $items = null;

            if (!empty($explicitIds)) {
                $items = OrderItem::whereIn('id', $explicitIds)->get();
                if ($items->isEmpty()) {
                    return ToolResult::success([
                        'scope'         => 'explicit',
                        'deleted_count' => 0,
                        'deleted'       => [],
                        'message'       => 'Keine OrderItems unter den angegebenen IDs gefunden.',
                    ]);
                }
                foreach ($items as $it) {
                    $ev = $it->eventDay?->event;
                    if (!$ev || !$context->user->teams()->where('teams.id', $ev->team_id)->exists()) {
                        return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf OrderItem #' . $it->id . '.');
                    }
                    $eventForAccess ??= $ev;
                }
            } else {
                $resolved = $this->resolveEvent($arguments, $context);
                if ($resolved instanceof ToolResult) {
                    return $resolved;
                }
                $eventForAccess = $resolved;
                $items = OrderItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $eventForAccess->id))->get();
                if ($items->isEmpty()) {
                    return ToolResult::success([
                        'scope'         => 'event',
                        'event_id'      => $eventForAccess->id,
                        'deleted_count' => 0,
                        'deleted'       => [],
                        'message'       => 'Event hat keine OrderItems.',
                    ]);
                }
            }

            $atomic = array_key_exists('atomic', $arguments) ? (bool) $arguments['atomic'] : true;

            $deleted = [];
            $failed = [];
            $positionsDeletedTotal = 0;

            $deleteOne = function (OrderItem $item) use (&$deleted, &$positionsDeletedTotal) {
                $positionsDeleted = (int) $item->posList()->count();
                $item->posList()->delete();
                $item->delete();
                $deleted[] = [
                    'id'                => (int) $item->id,
                    'uuid'              => $item->uuid,
                    'typ'               => $item->typ,
                    'positions_deleted' => $positionsDeleted,
                ];
                $positionsDeletedTotal += $positionsDeleted;
            };

            if ($atomic) {
                try {
                    DB::transaction(function () use ($items, $deleteOne) {
                        foreach ($items as $item) {
                            $deleteOne($item);
                        }
                    });
                } catch (\Throwable $e) {
                    return ToolResult::error('BULK_DELETE_FAILED',
                        'Atomic-Modus: erstes Fehler-Item hat alle Deletes zurueckgerollt. Detail: ' . $e->getMessage());
                }
            } else {
                foreach ($items as $item) {
                    try {
                        DB::transaction(function () use ($item, $deleteOne) {
                            $deleteOne($item);
                        });
                    } catch (\Throwable $e) {
                        $failed[] = ['id' => (int) $item->id, 'error' => $e->getMessage()];
                    }
                }
            }

            if (class_exists(ActivityLogger::class) && $eventForAccess) {
                ActivityLogger::log($eventForAccess, 'order',
                    sprintf('%d Bestell-Vorgang/Vorgaenge geloescht (soft, inkl. %d Positionen)', count($deleted), $positionsDeletedTotal));
            }

            return ToolResult::success([
                'scope'                 => !empty($explicitIds) ? 'explicit' : 'event',
                'event_id'              => $eventForAccess?->id,
                'deleted'               => $deleted,
                'deleted_count'         => count($deleted),
                'failed'                => $failed,
                'failed_count'          => count($failed),
                'positions_deleted_total' => $positionsDeletedTotal,
                'atomic'                => $atomic,
                'message'               => sprintf(
                    '%d Bestell-Vorgang/Vorgaenge geloescht, %d fehlgeschlagen (inkl. %d Position(en)).',
                    count($deleted), count($failed), $positionsDeletedTotal
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
            'tags'          => ['events', 'order', 'item', 'delete', 'bulk'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'destructive',
            'idempotent'    => false,
            'side_effects'  => ['deletes', 'cascade-deletes'],
        ];
    }
}
