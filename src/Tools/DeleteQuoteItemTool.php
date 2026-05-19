<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Services\ActivityLogger;

/**
 * Soft-Delete eines Angebots-Vorgangs (QuoteItem). Alle untergeordneten
 * QuotePositions werden via posList-Cascade ebenfalls soft-deleted.
 *
 * Nicht angefasst werden:
 *   - FlatRateApplications / LocationPricingApplications, die auf das Item
 *     verweisen — sie bleiben fuer den Audit-Trail erhalten (ihr `*_item_id`
 *     zeigt dann auf ein soft-deleted Item).
 *   - OrderItems, die per Convert/Sync aus diesem QuoteItem erzeugt wurden.
 */
class DeleteQuoteItemTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.quote-items.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/quote-items/{id} - Soft-Delete eines Angebots-Vorgangs (QuoteItem). '
            . 'Identifikation: item_id ODER uuid. Alle QuotePositions des Vorgangs werden '
            . 'kaskadiert soft-deleted. Applications (FlatRate, LocationPricing) bleiben fuer '
            . 'den Audit-Trail erhalten.';
    }

    public function getSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'item_id' => ['type' => 'integer', 'description' => 'ID des QuoteItems.'],
                'uuid'    => ['type' => 'string',  'description' => 'UUID des QuoteItems.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $query = QuoteItem::query();
            if (!empty($arguments['item_id'])) {
                $query->where('id', (int) $arguments['item_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'item_id oder uuid ist erforderlich.');
            }

            $item = $query->first();
            if (!$item) {
                return ToolResult::error('ITEM_NOT_FOUND', 'QuoteItem nicht gefunden.');
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
                ActivityLogger::log($event, 'quote', "Vorgang „{$typ}\" geloescht (soft, inkl. {$positionsDeleted} Positionen)");
            }

            return ToolResult::success([
                'id'                 => $id,
                'uuid'               => $uuid,
                'typ'                => $typ,
                'event_id'           => $event->id,
                'positions_deleted'  => $positionsDeleted,
                'message'            => "Vorgang „{$typ}\" geloescht (soft, inkl. {$positionsDeleted} Position(en)).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'quote', 'item', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'destructive',
            'idempotent'    => false,
            'side_effects'  => ['deletes', 'cascade-deletes'],
        ];
    }
}
