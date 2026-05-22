<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Massen-Soft-Delete mehrerer Angebots-Vorgaenge (QuoteItem). Pro Item
 * werden alle untergeordneten QuotePositions kaskadiert mit soft-deleted
 * (`posList()->delete()`).
 *
 * Scope-Optionen:
 *   - quote_item_ids[]: explizite Liste.
 *   - event_id|event_uuid|event_number + confirm_event_wide=true: alle QuoteItems
 *     des Events.
 *
 * Applications (FlatRate / LocationPricing) bleiben fuer den Audit-Trail erhalten.
 */
class BulkDeleteQuoteItemsTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.quote-items.bulk.DELETE';
    }

    public function getDescription(): string
    {
        return 'POST /events/quote-items/bulk/delete - Massen-Soft-Delete von Angebots-Vorgaengen (QuoteItem). '
            . 'SCOPE (genau einer): '
            . '(1) quote_item_ids[] – explizite Liste von Item-IDs. '
            . '(2) event_id|event_uuid|event_number + confirm_event_wide=true – ALLE QuoteItems des Events. '
            . 'Jeder Delete cascadet alle QuotePositions des Vorgangs (posList()->delete()). '
            . 'Atomic-Modus: atomic=true (Default) → alle Deletes in einer Transaction; '
            . 'atomic=false → pro Item eigene Transaction, Teil-Erfolge moeglich. '
            . 'Applications (FlatRate, LocationPricing) bleiben fuer Audit-Trail erhalten.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'quote_item_ids'     => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Explizite Liste von QuoteItem-IDs.'],
                'confirm_event_wide' => ['type' => 'boolean', 'description' => 'true = ALLE QuoteItems des angegebenen Events loeschen. event_* ist dann Pflicht.'],
                'atomic'             => ['type' => 'boolean', 'description' => 'Default true. Bei false: pro Item eigene Transaction, Teil-Erfolge moeglich.'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $idList = $arguments['quote_item_ids'] ?? [];
            $explicitIds = is_array($idList)
                ? array_values(array_unique(array_filter(array_map('intval', $idList))))
                : [];
            $confirmWide = (bool) ($arguments['confirm_event_wide'] ?? false);

            // Genau einer der beiden Scope-Pfade.
            if (empty($explicitIds) && !$confirmWide) {
                return ToolResult::error('VALIDATION_ERROR', 'Scope erforderlich: quote_item_ids[] ODER event_*+confirm_event_wide=true.');
            }
            if (!empty($explicitIds) && $confirmWide) {
                return ToolResult::error('VALIDATION_ERROR', 'quote_item_ids[] und confirm_event_wide=true gleichzeitig sind nicht erlaubt. Bitte genau einen Scope waehlen.');
            }

            $eventForAccess = null;
            $items = null;

            if (!empty($explicitIds)) {
                $items = QuoteItem::whereIn('id', $explicitIds)->get();
                if ($items->isEmpty()) {
                    return ToolResult::success([
                        'scope'          => 'explicit',
                        'deleted_count'  => 0,
                        'deleted_ids'    => [],
                        'message'        => 'Keine QuoteItems unter den angegebenen IDs gefunden.',
                    ]);
                }
                // Access-Check: alle Items muessen zum gleichen Event-Team gehoeren (oder zumindest dem User).
                foreach ($items as $it) {
                    $ev = $it->eventDay?->event;
                    if (!$ev || !$context->user->teams()->where('teams.id', $ev->team_id)->exists()) {
                        return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf QuoteItem #' . $it->id . '.');
                    }
                    $eventForAccess ??= $ev;
                }
            } else {
                $resolved = $this->resolveEvent($arguments, $context);
                if ($resolved instanceof ToolResult) {
                    return $resolved;
                }
                $eventForAccess = $resolved;
                $items = QuoteItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $eventForAccess->id))->get();
                if ($items->isEmpty()) {
                    return ToolResult::success([
                        'scope'         => 'event',
                        'event_id'      => $eventForAccess->id,
                        'deleted_count' => 0,
                        'deleted_ids'   => [],
                        'message'       => 'Event hat keine QuoteItems.',
                    ]);
                }
            }

            $atomic = array_key_exists('atomic', $arguments) ? (bool) $arguments['atomic'] : true;

            $deleted = [];
            $failed = [];
            $positionsDeletedTotal = 0;

            $deleteOne = function (QuoteItem $item) use (&$deleted, &$positionsDeletedTotal) {
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
                ActivityLogger::log($eventForAccess, 'quote',
                    sprintf('%d Vorgang/Vorgaenge geloescht (soft, inkl. %d Positionen)', count($deleted), $positionsDeletedTotal));
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
                    '%d Vorgang/Vorgaenge geloescht, %d fehlgeschlagen (inkl. %d Position(en)).',
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
            'tags'          => ['events', 'quote', 'item', 'delete', 'bulk'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'destructive',
            'idempotent'    => false,
            'side_effects'  => ['deletes', 'cascade-deletes'],
        ];
    }
}
