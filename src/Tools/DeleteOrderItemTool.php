<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\OrderItem;
use Platform\Events\Services\ActivityLogger;

/**
 * Soft-Delete eines Bestell-Vorgangs (OrderItem). Alle untergeordneten
 * OrderPositions werden via posList-Cascade ebenfalls soft-deleted.
 *
 * Verbindungen zu QuoteItems (per Convert/Sync) werden NICHT zurueck-
 * gerollt — das QuoteItem bleibt eigenstaendig erhalten.
 */
class DeleteOrderItemTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.order-items.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/order-items/{id} - Soft-Delete eines Bestell-Vorgangs (OrderItem). '
            . 'Identifikation: item_id ODER uuid. Alle OrderPositions des Vorgangs werden '
            . 'kaskadiert soft-deleted. Ein eventuell verknuepftes QuoteItem bleibt erhalten.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'item_id' => ['type' => 'integer', 'description' => 'ID des OrderItems.'],
                'uuid'    => ['type' => 'string',  'description' => 'UUID des OrderItems.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $query = OrderItem::query();
            if (!empty($arguments['item_id'])) {
                $query->where('id', (int) $arguments['item_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'item_id oder uuid ist erforderlich.');
            }

            $item = $query->first();
            if (!$item) {
                return ToolResult::error('ITEM_NOT_FOUND', 'OrderItem nicht gefunden.');
            }

            $event = $item->eventDay?->event;
            if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diesen Vorgang.');
            }

            $id   = $item->id;
            $uuid = $item->uuid;
            $typ  = $item->typ;
            $positionsDeleted = $item->posList()->count();

            // Cascade: posList soft-deleten, dann Item selbst.
            $item->posList()->delete();
            $item->delete();

            if (class_exists(ActivityLogger::class)) {
                ActivityLogger::log($event, 'order', "Bestell-Vorgang „{$typ}\" geloescht (soft, inkl. {$positionsDeleted} Positionen)");
            }

            return ToolResult::success([
                'id'                 => $id,
                'uuid'               => $uuid,
                'typ'                => $typ,
                'event_id'           => $event->id,
                'positions_deleted'  => $positionsDeleted,
                'message'            => "Bestell-Vorgang „{$typ}\" geloescht (soft, inkl. {$positionsDeleted} Position(en)).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'order', 'item', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'destructive',
            'idempotent'    => false,
            'side_effects'  => ['deletes', 'cascade-deletes'],
        ];
    }
}
