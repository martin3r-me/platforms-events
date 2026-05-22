<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;
use Platform\Events\Models\OrderItem;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Massen-Anlage mehrerer Bestell-Vorgaenge (OrderItem) in einem Call.
 * Analog zu BulkCreateQuoteItemsTool — Unterschied: OrderItem hat
 * `lieferant`/`price_mode` statt `mwst`/`beverage_mode`.
 */
class BulkCreateOrderItemsTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.order-items.bulk.CREATE';
    }

    public function getDescription(): string
    {
        return 'POST /events/order-items/bulk - Massen-Anlage von Bestell-Vorgaengen (OrderItem). '
            . 'SCOPE: event_day_id|event_day_uuid als Default fuer alle Rows, ODER pro Row eigene '
            . 'event_day_id/uuid mitgeben. Jede Row akzeptiert die gleichen Felder wie '
            . 'events.order-items.CREATE (typ pflicht; status/lieferant/price_mode optional). '
            . 'sort_order wird automatisch fortlaufend ab max+1 pro EventDay vergeben. '
            . 'Atomic-Modus: atomic=true (Default) → alle in einer Transaction; atomic=false → '
            . 'pro Row eigene Transaction, Teil-Erfolge moeglich.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'event_day_id'   => ['type' => 'integer'],
                'event_day_uuid' => ['type' => 'string'],
                'atomic'         => ['type' => 'boolean'],
                'items' => [
                    'type'        => 'array',
                    'description' => 'Liste der anzulegenden Bestell-Vorgaenge.',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'event_day_id'   => ['type' => 'integer'],
                            'event_day_uuid' => ['type' => 'string'],
                            'typ'            => ['type' => 'string', 'description' => 'Pflicht: Vorgangs-Typ.'],
                            'status'         => ['type' => 'string', 'description' => 'Optional (Default "Offen").'],
                            'lieferant'      => ['type' => 'string'],
                            'price_mode'     => ['type' => 'string', 'description' => 'Optional "netto" (Default) oder "brutto".'],
                        ],
                        'required' => ['typ'],
                    ],
                ],
            ]),
            'required' => ['items'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $items = is_array($arguments['items'] ?? null) ? $arguments['items'] : [];
            if (empty($items)) {
                return ToolResult::error('VALIDATION_ERROR', 'items[] darf nicht leer sein.');
            }

            $eventForAccess = null;
            if (!empty($arguments['event_id']) || !empty($arguments['event_uuid']) || !empty($arguments['event_number'])) {
                $resolved = $this->resolveEvent($arguments, $context);
                if ($resolved instanceof ToolResult) {
                    return $resolved;
                }
                $eventForAccess = $resolved;
            }

            $defaultDay = null;
            if (!empty($arguments['event_day_id'])) {
                $defaultDay = EventDay::find((int) $arguments['event_day_id']);
            } elseif (!empty($arguments['event_day_uuid'])) {
                $defaultDay = EventDay::where('uuid', $arguments['event_day_uuid'])->first();
            }
            if ($defaultDay && $eventForAccess && $defaultDay->event_id !== $eventForAccess->id) {
                return ToolResult::error('VALIDATION_ERROR', 'event_day gehoert nicht zum angegebenen Event.');
            }

            $atomic = array_key_exists('atomic', $arguments) ? (bool) $arguments['atomic'] : true;

            $created          = [];
            $failed           = [];
            $touchedEvents    = [];
            $sortOffsetByDay  = [];

            $processRow = function (array $row, int $index) use ($context, $defaultDay, $eventForAccess, &$created, &$touchedEvents, &$sortOffsetByDay): ?string {
                $day = $defaultDay;
                if (!empty($row['event_day_id'])) {
                    $day = EventDay::find((int) $row['event_day_id']);
                } elseif (!empty($row['event_day_uuid'])) {
                    $day = EventDay::where('uuid', $row['event_day_uuid'])->first();
                }
                if (!$day) {
                    return 'Row[' . $index . ']: event_day_id/event_day_uuid fehlt oder EventDay nicht gefunden.';
                }
                $event = $day->event;
                if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                    return 'Row[' . $index . ']: Kein Zugriff auf das Event.';
                }
                if ($eventForAccess && $event->id !== $eventForAccess->id) {
                    return 'Row[' . $index . ']: EventDay gehoert nicht zum angegebenen Event.';
                }

                $typ = trim((string) ($row['typ'] ?? ''));
                if ($typ === '') {
                    return 'Row[' . $index . ']: typ ist erforderlich.';
                }

                $dayId = (int) $day->id;
                if (!isset($sortOffsetByDay[$dayId])) {
                    $sortOffsetByDay[$dayId] = (int) OrderItem::where('event_day_id', $dayId)->max('sort_order');
                }
                $sortOffsetByDay[$dayId]++;

                $item = OrderItem::create([
                    'team_id'      => $event->team_id,
                    'user_id'      => Auth::id() ?: $context->user->id,
                    'event_day_id' => $day->id,
                    'typ'          => $typ,
                    'status'       => $row['status']     ?? 'Offen',
                    'lieferant'    => $row['lieferant']  ?? null,
                    'price_mode'   => $row['price_mode'] ?? 'netto',
                    'sort_order'   => $sortOffsetByDay[$dayId],
                ]);

                $created[] = [
                    'index'        => $index,
                    'id'           => $item->id,
                    'uuid'         => $item->uuid,
                    'event_day_id' => $item->event_day_id,
                    'typ'          => $item->typ,
                    'status'       => $item->status,
                ];
                $touchedEvents[$event->id] = true;
                return null;
            };

            if ($atomic) {
                try {
                    DB::transaction(function () use ($items, $processRow) {
                        foreach ($items as $index => $row) {
                            if (!is_array($row)) {
                                throw new \RuntimeException('Row[' . $index . ']: Item muss ein Objekt sein.');
                            }
                            $err = $processRow($row, (int) $index);
                            if ($err !== null) {
                                throw new \RuntimeException($err);
                            }
                        }
                    });
                } catch (\Throwable $e) {
                    return ToolResult::error('BULK_CREATE_FAILED',
                        'Atomic-Modus: erste Fehler-Row hat alles zurueckgerollt. Detail: ' . $e->getMessage());
                }
            } else {
                foreach ($items as $index => $row) {
                    if (!is_array($row)) {
                        $failed[] = ['index' => (int) $index, 'error' => 'Item muss ein Objekt sein.'];
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

            return ToolResult::success([
                'created'         => $created,
                'failed'          => $failed,
                'created_count'   => count($created),
                'failed_count'    => count($failed),
                'affected_events' => array_values(array_keys($touchedEvents)),
                'atomic'          => $atomic,
                'message'         => sprintf(
                    '%d Bestell-Vorgang/Vorgaenge angelegt, %d fehlgeschlagen (auf %d Event(s)).',
                    count($created), count($failed), count($touchedEvents)
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
            'tags'          => ['events', 'order', 'item', 'create', 'bulk'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'moderate',
            'idempotent'    => false,
            'side_effects'  => ['inserts'],
        ];
    }
}
