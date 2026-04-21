<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Listet Angebots-Vorgänge (QuoteItems) eines Events — ein Vorgang je Tag+Typ.
 */
class ListQuoteItemsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.quote-items.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/quote-items - Listet Angebots-Vorgänge (Speisen/Getränke/Personal etc.) pro Tag. '
            . 'Event via event_id/event_uuid/event_number. Optional Filter: event_day_id, typ, status.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => array_merge($this->eventSelectorSchema(), [
                    'event_day_id' => ['type' => 'integer', 'description' => 'Optional: nur Vorgänge dieses Tages.'],
                    'typ'          => ['type' => 'string',  'description' => 'Optional: typ-Filter (z.B. "Speisen").'],
                    'status'       => ['type' => 'string',  'description' => 'Optional: Status-Filter.'],
                ]),
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) return $event;

            $dayIds = $event->days()->pluck('id');
            $query = QuoteItem::whereIn('event_day_id', $dayIds);

            if (!empty($arguments['event_day_id'])) {
                $query->where('event_day_id', (int) $arguments['event_day_id']);
            }
            if (!empty($arguments['typ'])) {
                $query->where('typ', $arguments['typ']);
            }
            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            $this->applyStandardFilters($query, $arguments, ['typ', 'status', 'event_day_id']);
            $this->applyStandardSort($query, $arguments, ['sort_order', 'created_at'], 'sort_order', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $items = $query->get()->map(fn (QuoteItem $i) => [
                'id'           => $i->id,
                'uuid'         => $i->uuid,
                'event_day_id' => $i->event_day_id,
                'typ'          => $i->typ,
                'status'       => $i->status,
                'mwst'         => $i->mwst,
                'artikel'      => (int) $i->artikel,
                'positionen'   => (int) $i->positionen,
                'umsatz'       => (float) $i->umsatz,
                'sort_order'   => $i->sort_order,
            ])->toArray();

            return ToolResult::success([
                'quote_items' => $items,
                'count'       => count($items),
                'event_id'    => $event->id,
                'message'     => count($items) . ' Angebots-Vorgang/Vorgänge für Event ' . $event->event_number . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Angebots-Vorgänge: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query', 'tags' => ['events', 'quote', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
