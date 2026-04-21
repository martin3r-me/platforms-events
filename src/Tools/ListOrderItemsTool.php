<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\OrderItem;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class ListOrderItemsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.order-items.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/order-items - Listet Bestell-Vorgänge pro Tag. Optional Filter: event_day_id, typ, status, lieferant.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            ['properties' => array_merge($this->eventSelectorSchema(), [
                'event_day_id' => ['type' => 'integer'], 'typ' => ['type' => 'string'],
                'status' => ['type' => 'string'], 'lieferant' => ['type' => 'string'],
            ])]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) return $event;

            $dayIds = $event->days()->pluck('id');
            $query = OrderItem::whereIn('event_day_id', $dayIds);

            foreach (['event_day_id' => 'event_day_id', 'typ' => 'typ', 'status' => 'status', 'lieferant' => 'lieferant'] as $arg => $col) {
                if (!empty($arguments[$arg])) $query->where($col, $arguments[$arg]);
            }

            $this->applyStandardFilters($query, $arguments, ['typ', 'status', 'lieferant']);
            $this->applyStandardSort($query, $arguments, ['sort_order', 'created_at'], 'sort_order', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $items = $query->get()->map(fn (OrderItem $i) => [
                'id' => $i->id, 'uuid' => $i->uuid, 'event_day_id' => $i->event_day_id,
                'typ' => $i->typ, 'status' => $i->status, 'lieferant' => $i->lieferant,
                'artikel' => (int) $i->artikel, 'positionen' => (int) $i->positionen,
                'einkauf' => (float) $i->einkauf, 'sort_order' => $i->sort_order,
            ])->toArray();

            return ToolResult::success([
                'order_items' => $items, 'count' => count($items), 'event_id' => $event->id,
                'message' => count($items) . ' Bestell-Vorgang/Vorgänge.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category' => 'query', 'tags' => ['events', 'order', 'list'], 'read_only' => true,
            'requires_auth' => true, 'requires_team' => false, 'risk_level' => 'safe', 'idempotent' => true];
    }
}
