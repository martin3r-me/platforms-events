<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;
use Platform\Events\Models\ScheduleItem;
use Platform\Events\Tools\Concerns\NormalizesTimeFields;

class UpdateScheduleItemTool implements ToolContract, ToolMetadataContract
{
    use NormalizesTimeFields;

    protected const STRING_FIELDS = ['datum', 'von', 'bis', 'beschreibung', 'raum', 'bemerkung'];

    public function getName(): string
    {
        return 'events.schedule-items.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/schedule/{id} - Aktualisiert einen Ablauf-Eintrag. '
            . 'Identifikation: schedule_id (Alias schedule_item_id) ODER uuid. '
            . 'Felder (alle optional): '
            . 'beschreibung (string), datum (YYYY-MM-DD oder Freitext), '
            . 'von (HH:MM), bis (HH:MM), raum, bemerkung, '
            . 'linked (boolean: manuelle Block-Verbindung zum vorherigen Eintrag, KEIN Tag-Link), '
            . 'sort_order (int). '
            . 'Convenience: event_day_id|day_id zieht datum aus events_event_days.id (wenn datum nicht uebergeben). '
            . 'Response liefert matched_event_day_id (Tag mit gleichem datum, sofern vorhanden).';
    }

    public function getSchema(): array
    {
        $props = [
            'schedule_id'      => ['type' => 'integer'],
            'schedule_item_id' => ['type' => 'integer', 'description' => 'Alias fuer schedule_id.'],
            'uuid'             => ['type' => 'string'],
            'linked'           => ['type' => 'boolean'],
            'sort_order'       => ['type' => 'integer'],
            'event_day_id'     => ['type' => 'integer', 'description' => 'Convenience: zieht datum aus events_event_days.id.'],
            'day_id'           => ['type' => 'integer', 'description' => 'Alias fuer event_day_id.'],
        ];
        foreach (self::STRING_FIELDS as $f) {
            $props[$f] = ['type' => 'string'];
        }
        return ['type' => 'object', 'properties' => $props];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = ScheduleItem::query();
            $idAlias = $arguments['schedule_id'] ?? ($arguments['schedule_item_id'] ?? null);
            if (!empty($idAlias)) {
                $query->where('id', (int) $idAlias);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'schedule_id (oder schedule_item_id) oder uuid ist erforderlich.');
            }

            $item = $query->first();
            if (!$item) {
                return ToolResult::error('SCHEDULE_NOT_FOUND', 'Ablauf-Eintrag nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $item->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            // Aliases zwischen Tag/Buchung/Englisch normalisieren.
            $aliasesApplied = $this->normalizeTimeFields($arguments, ['start' => 'von', 'end' => 'bis']);

            // Convenience: event_day_id|day_id → datum aus Tag uebernehmen.
            $dayIdInput = $arguments['event_day_id'] ?? ($arguments['day_id'] ?? null);
            if ($dayIdInput && empty($arguments['datum'])) {
                $linkedDay = EventDay::where('event_id', $item->event_id)->find((int) $dayIdInput);
                if ($linkedDay && $linkedDay->datum) {
                    $arguments['datum'] = $linkedDay->datum->format('Y-m-d');
                    $aliasesApplied[] = 'event_day_id→datum';
                }
            }

            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f];
                }
            }
            if (array_key_exists('linked', $arguments)) {
                $update['linked'] = (bool) $arguments['linked'];
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int) $arguments['sort_order'];
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $known = array_merge(
                ['schedule_id', 'schedule_item_id', 'uuid', 'linked', 'sort_order', 'event_day_id', 'day_id'],
                self::STRING_FIELDS,
                $this->timeFieldAliases(),
            );
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $item->update($update);

            // Tag-Match (logisch, kein FK).
            $matchedDayId = null;
            if ($item->datum) {
                $datumStr = is_string($item->datum) ? $item->datum : $item->datum->format('Y-m-d');
                $matchedDayId = EventDay::where('event_id', $item->event_id)
                    ->whereDate('datum', $datumStr)
                    ->value('id');
            }

            return ToolResult::success([
                'id'                   => $item->id,
                'uuid'                 => $item->uuid,
                'event_id'             => $item->event_id,
                'datum'                => $item->datum,
                'von'                  => $item->von,
                'bis'                  => $item->bis,
                'beschreibung'         => $item->beschreibung,
                'raum'                 => $item->raum,
                'bemerkung'            => $item->bemerkung,
                // linked = manuelles UI-Block-Flag, Tag-Zuordnung via is_day_linked / matched_event_day_id.
                'linked'               => (bool) $item->linked,
                'sort_order'           => $item->sort_order,
                'matched_event_day_id' => $matchedDayId,
                'is_day_linked'        => $matchedDayId !== null,
                'aliases_applied'      => $aliasesApplied,
                'ignored_fields'       => $ignored,
                '_field_hints'         => [
                    'linked'               => 'UI-Block-Flag: optisch verbunden mit dem Eintrag DARUEBER (zwei Halb-Bloecke werden als ein Block dargestellt). NICHT die Tag-Zuordnung.',
                    'is_day_linked'        => 'Abgeleitet: true wenn datum mit einem EventDay matcht. Nutze stattdessen matched_event_day_id, um den konkreten Tag zu identifizieren.',
                    'matched_event_day_id' => 'EventDay-ID, dessen datum mit dem ScheduleItem-datum uebereinstimmt (logisch, kein FK).',
                    'raum'                 => 'Freitext-Raumkuerzel. Sollte einem in den Buchungen des Events vorhandenen Raum entsprechen, sonst nicht im UI-Dropdown auswaehlbar.',
                ],
                'message'              => 'Ablauf-Eintrag aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'schedule', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
