<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;
use Platform\Events\Models\OrderItem;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class CreateOrderItemTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.order-items.CREATE';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/days/{day}/order-items - Legt einen Bestell-Vorgang an einem Event-Tag an.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'event_day_id'   => ['type' => 'integer'],
                'event_day_uuid' => ['type' => 'string'],
                'typ'            => ['type' => 'string', 'description' => 'Vorgangs-Typ.'],
                'status'         => ['type' => 'string', 'description' => 'Optional (default "Offen").'],
                'lieferant'      => ['type' => 'string', 'description' => 'Optional: Lieferant.'],
            ]),
            'required' => ['typ'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) return $event;

            $day = null;
            if (!empty($arguments['event_day_id'])) {
                $day = EventDay::where('event_id', $event->id)->find($arguments['event_day_id']);
            } elseif (!empty($arguments['event_day_uuid'])) {
                $day = EventDay::where('event_id', $event->id)->where('uuid', $arguments['event_day_uuid'])->first();
            }
            if (!$day) return ToolResult::error('VALIDATION_ERROR', 'Event-Tag fehlt.');

            $typ = trim((string) ($arguments['typ'] ?? ''));
            if ($typ === '') return ToolResult::error('VALIDATION_ERROR', 'typ ist erforderlich.');

            $maxSort = (int) OrderItem::where('event_day_id', $day->id)->max('sort_order');
            $item = OrderItem::create([
                'team_id'      => $event->team_id,
                'user_id'      => Auth::id(),
                'event_day_id' => $day->id,
                'typ'          => $typ,
                'status'       => $arguments['status']    ?? 'Offen',
                'lieferant'    => $arguments['lieferant'] ?? null,
                'sort_order'   => $maxSort + 1,
            ]);

            return ToolResult::success([
                'order_item' => ['id' => $item->id, 'uuid' => $item->uuid, 'typ' => $item->typ, 'status' => $item->status],
                'message' => "Bestell-Vorgang «{$item->typ}» angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category' => 'mutation', 'tags' => ['events', 'order', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'moderate', 'idempotent' => false];
    }
}
