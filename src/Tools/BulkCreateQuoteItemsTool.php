<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Massen-Anlage mehrerer Angebots-Vorgaenge (QuoteItem) in einem einzigen
 * Call. Use-Case: LLM erzeugt aus einer Anfrage gleich mehrere Vorgaenge
 * (Speisen / Getraenke / Personal / Equipment) pro Event-Tag.
 *
 * Scope-Optionen:
 *   - event_day_id|event_day_uuid (Top-Level): Default-Tag fuer alle Rows.
 *   - keine Top-Level-Angabe: jede Row muss eigenen event_day_id|event_day_uuid
 *     mitbringen (Rows duerfen den Default ueberschreiben).
 *
 * sort_order wird automatisch fortlaufend ab `max + 1` pro EventDay vergeben.
 *
 * Atomic-Modus:
 *   - atomic=true (Default): alle Rows in einer DB-Transaction.
 *   - atomic=false: pro Row eigene Transaction, Teil-Erfolge moeglich.
 */
class BulkCreateQuoteItemsTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.quote-items.bulk.CREATE';
    }

    public function getDescription(): string
    {
        return 'POST /events/quote-items/bulk - Massen-Anlage von Angebots-Vorgaengen (QuoteItem). '
            . 'SCOPE: event_day_id|event_day_uuid als Default fuer alle Rows, ODER pro Row eigene '
            . 'event_day_id/uuid mitgeben. Jede Row akzeptiert die gleichen Felder wie '
            . 'events.quote-items.CREATE (typ pflicht; status/mwst/beverage_mode optional). '
            . 'sort_order wird automatisch fortlaufend ab max+1 pro EventDay vergeben. '
            . 'Atomic-Modus: atomic=true (Default) → alle in einer Transaction; atomic=false → '
            . 'pro Row eigene Transaction, Teil-Erfolge moeglich.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'event_day_id'   => ['type' => 'integer', 'description' => 'Default-Scope: alle Rows ohne eigene event_day_id landen hier.'],
                'event_day_uuid' => ['type' => 'string',  'description' => 'Alternative zu event_day_id.'],
                'atomic'         => ['type' => 'boolean', 'description' => 'Default true. Bei false: pro Row eigene Transaction, Teil-Erfolge moeglich.'],
                'items' => [
                    'type'        => 'array',
                    'description' => 'Liste der anzulegenden Angebots-Vorgaenge.',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'event_day_id'   => ['type' => 'integer', 'description' => 'Optional: ueberschreibt den Default-EventDay fuer diese Row.'],
                            'event_day_uuid' => ['type' => 'string'],
                            'typ'            => ['type' => 'string', 'description' => 'Pflicht: Vorgangs-Typ (Speisen/Getraenke/Personal/Equipment).'],
                            'status'         => ['type' => 'string', 'description' => 'Optional (Default "Entwurf").'],
                            'mwst'           => ['type' => 'string', 'description' => 'Optional (Default "19%").'],
                            'beverage_mode'  => ['type' => 'string', 'description' => 'Optional Default-Modus fuer Getraenke-Positionen dieses Vorgangs.'],
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

            // Event-Resolver optional am Top-Level (ergibt sich sonst aus dem EventDay pro Row).
            $eventForAccess = null;
            if (!empty($arguments['event_id']) || !empty($arguments['event_uuid']) || !empty($arguments['event_number'])) {
                $resolved = $this->resolveEvent($arguments, $context);
                if ($resolved instanceof ToolResult) {
                    return $resolved;
                }
                $eventForAccess = $resolved;
            }

            // Default-EventDay.
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
            $sortOffsetByDay  = []; // event_day_id → naechster sort_order

            $processRow = function (array $row, int $index) use ($context, $defaultDay, $eventForAccess, &$created, &$touchedEvents, &$sortOffsetByDay): ?string {
                // EventDay pro Row aufloesen (Row > Default).
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

                $beverageMode = isset($row['beverage_mode']) && trim((string) $row['beverage_mode']) !== ''
                    ? trim((string) $row['beverage_mode'])
                    : null;

                // sort_order auto-fortlaufend pro EventDay.
                $dayId = (int) $day->id;
                if (!isset($sortOffsetByDay[$dayId])) {
                    $sortOffsetByDay[$dayId] = (int) QuoteItem::where('event_day_id', $dayId)->max('sort_order');
                }
                $sortOffsetByDay[$dayId]++;

                $item = QuoteItem::create([
                    'team_id'       => $event->team_id,
                    'user_id'       => Auth::id() ?: $context->user->id,
                    'event_day_id'  => $day->id,
                    'typ'           => $typ,
                    'status'        => $row['status'] ?? 'Entwurf',
                    'mwst'          => $row['mwst']   ?? '19%',
                    'beverage_mode' => $beverageMode,
                    'sort_order'    => $sortOffsetByDay[$dayId],
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
                'created'        => $created,
                'failed'         => $failed,
                'created_count'  => count($created),
                'failed_count'   => count($failed),
                'affected_events' => array_values(array_keys($touchedEvents)),
                'atomic'         => $atomic,
                'message'        => sprintf(
                    '%d Vorgang/Vorgaenge angelegt, %d fehlgeschlagen (auf %d Event(s)).',
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
            'tags'          => ['events', 'quote', 'item', 'create', 'bulk'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'moderate',
            'idempotent'    => false,
            'side_effects'  => ['inserts'],
        ];
    }
}
