<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ScheduleItem;
use Platform\Events\Tools\Concerns\NormalizesTimeFields;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Massen-Update mehrerer Ablauf-Eintraege eines Events. Filter + Setzwerte
 * werden in einem Call uebergeben – kein N-fach-PATCH mehr noetig.
 */
class BulkUpdateScheduleItemsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;
    use NormalizesTimeFields;

    protected const SETTABLE_STRING_FIELDS = ['datum', 'von', 'bis', 'beschreibung', 'raum', 'bemerkung'];

    public function getName(): string
    {
        return 'events.schedule-items.bulk.PATCH';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/schedule/bulk - Massen-Update von Ablauf-Eintraegen. '
            . 'Pflicht: event-Selector + mindestens ein Filter (day_id|event_day_id, datum, schedule_ids[], '
            . 'schedule_item_ids[], description_contains) ODER confirm_event_wide=true (gilt fuer alle Eintraege des Events). '
            . 'Setzwerte (set, mindestens einer): raum, bemerkung, datum, von, bis, linked, sort_order. '
            . 'Tag-Aliases (von/bis/start_time/end_time) werden in den Setzwerten unterstuetzt. '
            . 'Response: count (aktualisiert), affected_ids[], set_fields[], skipped_ids[] (mit Grund).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                // Filter
                'event_day_id'         => ['type' => 'integer', 'description' => 'Filter: nur Items mit gleichem datum wie dieser Tag.'],
                'day_id'               => ['type' => 'integer', 'description' => 'Alias fuer event_day_id.'],
                'datum'                => ['type' => 'string',  'description' => 'Filter: nur Items mit diesem datum (YYYY-MM-DD).'],
                'schedule_ids'         => ['type' => 'array',   'items' => ['type' => 'integer'], 'description' => 'Filter: explizite Whitelist von Schedule-Item-IDs.'],
                'schedule_item_ids'    => ['type' => 'array',   'items' => ['type' => 'integer'], 'description' => 'Alias fuer schedule_ids.'],
                'description_contains' => ['type' => 'string',  'description' => 'Filter: Substring (case-insensitive) im beschreibung-Feld.'],
                'confirm_event_wide'   => ['type' => 'boolean', 'description' => 'Wenn kein anderer Filter gesetzt ist, muss true uebergeben werden, um wirklich ALLE Schedule-Items des Events zu aendern.'],
                // Setzwerte – verschachteltes "set"-Objekt
                'set' => [
                    'type' => 'object',
                    'description' => 'Werte, die in alle gefilterten Items geschrieben werden. Mindestens eines erforderlich.',
                    'properties' => [
                        'raum'         => ['type' => 'string'],
                        'bemerkung'    => ['type' => 'string'],
                        'datum'        => ['type' => 'string'],
                        'von'          => ['type' => 'string'],
                        'bis'          => ['type' => 'string'],
                        'beschreibung' => ['type' => 'string'],
                        'linked'       => ['type' => 'boolean'],
                        'sort_order'   => ['type' => 'integer'],
                    ],
                ],
            ]),
            'required' => ['set'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) {
                return $event;
            }

            // Set-Werte einsammeln + Aliases auf primaere Feldnamen mappen.
            $set = is_array($arguments['set'] ?? null) ? $arguments['set'] : [];
            $aliasesApplied = $this->normalizeTimeFields($set, ['start' => 'von', 'end' => 'bis']);

            $update = [];
            foreach (self::SETTABLE_STRING_FIELDS as $f) {
                if (array_key_exists($f, $set)) {
                    $update[$f] = $set[$f];
                }
            }
            if (array_key_exists('linked', $set)) {
                $update['linked'] = (bool) $set['linked'];
            }
            if (array_key_exists('sort_order', $set)) {
                $update['sort_order'] = (int) $set['sort_order'];
            }
            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'set: mindestens ein Setzwert ist erforderlich.');
            }

            // Filter aufbauen
            $query = ScheduleItem::where('event_id', $event->id);

            $hasFilter = false;

            $dayIdInput = $arguments['event_day_id'] ?? ($arguments['day_id'] ?? null);
            if (!empty($dayIdInput)) {
                $day = \Platform\Events\Models\EventDay::where('event_id', $event->id)->find((int) $dayIdInput);
                if (!$day) {
                    return ToolResult::error('VALIDATION_ERROR', 'event_day_id gehoert nicht zum Event.');
                }
                if ($day->datum) {
                    $query->whereDate('datum', $day->datum->format('Y-m-d'));
                    $hasFilter = true;
                }
            }
            if (!empty($arguments['datum'])) {
                $query->whereDate('datum', $arguments['datum']);
                $hasFilter = true;
            }
            $idList = $arguments['schedule_ids'] ?? ($arguments['schedule_item_ids'] ?? []);
            if (is_array($idList) && !empty($idList)) {
                $ids = array_values(array_unique(array_filter(array_map('intval', $idList))));
                $query->whereIn('id', $ids);
                $hasFilter = true;
            }
            if (!empty($arguments['description_contains'])) {
                $query->where('beschreibung', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $arguments['description_contains']) . '%');
                $hasFilter = true;
            }

            if (!$hasFilter && empty($arguments['confirm_event_wide'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein Filter angegeben. Setze entweder event_day_id/datum/schedule_ids/description_contains ODER confirm_event_wide=true, um ALLE Items zu aktualisieren.');
            }

            $items = $query->get();
            if ($items->isEmpty()) {
                return ToolResult::success([
                    'event_id'     => $event->id,
                    'count'        => 0,
                    'affected_ids' => [],
                    'set_fields'   => array_keys($update),
                    'message'      => 'Keine Schedule-Items entsprechen dem Filter.',
                ]);
            }

            $known = array_merge(
                ['event_id', 'event_uuid', 'event_number',
                 'event_day_id', 'day_id', 'datum',
                 'schedule_ids', 'schedule_item_ids', 'description_contains',
                 'confirm_event_wide', 'set'],
            );
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $affected = [];
            foreach ($items as $item) {
                $item->update($update);
                $affected[] = $item->id;
            }

            return ToolResult::success([
                'event_id'        => $event->id,
                'event_number'    => $event->event_number,
                'count'           => count($affected),
                'affected_ids'    => $affected,
                'set_fields'      => array_keys($update),
                'aliases_applied' => $aliasesApplied,
                'ignored_fields'  => $ignored,
                '_field_hints'    => [
                    'raum'   => 'Freitext-Raumkuerzel. Sollte einem in den Buchungen vorhandenen Raum entsprechen.',
                    'linked' => 'UI-Block-Flag (Verbindung mit Eintrag DARUEBER). Bulk-Setzen nur bewusst nutzen.',
                ],
                'message' => count($affected) . ' Ablauf-Eintraege aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Update: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'schedule', 'update', 'bulk'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
