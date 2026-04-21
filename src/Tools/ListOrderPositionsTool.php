<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;

class ListOrderPositionsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'events.order-positions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/order-items/{id}/positions - Listet Positionen eines Bestell-Vorgangs.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            ['properties' => [
                'order_item_id'   => ['type' => 'integer'],
                'order_item_uuid' => ['type' => 'string'],
                'gruppe'          => ['type' => 'string'],
            ]]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) return ToolResult::error('AUTH_ERROR', 'Kein User.');

            $orderItem = null;
            if (!empty($arguments['order_item_id'])) {
                $orderItem = OrderItem::find($arguments['order_item_id']);
            } elseif (!empty($arguments['order_item_uuid'])) {
                $orderItem = OrderItem::where('uuid', $arguments['order_item_uuid'])->first();
            }
            if (!$orderItem) return ToolResult::error('NOT_FOUND', 'OrderItem nicht gefunden.');

            $event = $orderItem->eventDay?->event;
            if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            $query = OrderPosition::where('order_item_id', $orderItem->id);
            if (!empty($arguments['gruppe'])) $query->where('gruppe', $arguments['gruppe']);
            $this->applyStandardFilters($query, $arguments, ['gruppe', 'name', 'mwst']);
            $this->applyStandardSearch($query, $arguments, ['name', 'gruppe', 'bemerkung']);
            $this->applyStandardSort($query, $arguments, ['sort_order', 'created_at'], 'sort_order', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $positions = $query->get()->map(fn (OrderPosition $p) => [
                'id' => $p->id, 'uuid' => $p->uuid,
                'gruppe' => $p->gruppe, 'name' => $p->name,
                'anz' => $p->anz, 'anz2' => $p->anz2,
                'uhrzeit' => $p->uhrzeit, 'bis' => $p->bis,
                'gebinde' => $p->gebinde, 'basis_ek' => (float) $p->basis_ek,
                'ek' => (float) $p->ek, 'mwst' => $p->mwst,
                'gesamt' => (float) $p->gesamt, 'bemerkung' => $p->bemerkung,
                'sort_order' => $p->sort_order,
            ])->toArray();

            return ToolResult::success([
                'positions' => $positions, 'count' => count($positions),
                'order_item_id' => $orderItem->id, 'typ' => $orderItem->typ,
                'message' => count($positions) . ' Position(en).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category' => 'query', 'tags' => ['events', 'order', 'position', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true];
    }
}
