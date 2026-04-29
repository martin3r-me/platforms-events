<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;
use Platform\Events\Models\ScheduleItem;
use Platform\Events\Tools\Concerns\NormalizesTimeFields;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class CreateScheduleItemTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;
    use NormalizesTimeFields;

    public function getName(): string
    {
        return 'events.schedule-items.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/schedule - Legt einen Ablaufplan-Eintrag an. '
            . 'Pflicht: event-Selector + beschreibung (z.B. "Empfang", "Vortrag"; siehe Settings → Ablaufplan-Beschreibungen). '
            . 'Felder: '
            . 'datum (YYYY-MM-DD; sonst freitext), '
            . 'von (HH:MM, Beginn), bis (HH:MM, Ende), '
            . 'raum (string, freitext), '
            . 'bemerkung (Freitext-Notiz). '
            . 'Convenience: event_day_id|day_id (FK events_event_days.id) – wenn gesetzt, wird datum aus dem Tag uebernommen, '
            . 'falls datum nicht explizit mitgegeben wurde. ScheduleItems haben keinen FK auf den Tag; die '
            . 'Verknuepfung erfolgt logisch ueber identische datum-Werte. '
            . 'WICHTIG: linked (boolean, default false) ist KEIN Tag-Link, sondern eine manuelle '
            . 'Block-Verbindung mit dem direkt darueber liegenden Eintrag (UI-Feature fuer optisch zusammengehoerige '
            . 'Bloecke). Tag-Match wird im Result als matched_event_day_id zurueckgegeben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'beschreibung'  => ['type' => 'string'],
                'datum'         => ['type' => 'string',  'description' => 'YYYY-MM-DD oder Freitext.'],
                'von'           => ['type' => 'string'],
                'bis'           => ['type' => 'string'],
                'raum'          => ['type' => 'string'],
                'bemerkung'     => ['type' => 'string'],
                'linked'        => ['type' => 'boolean', 'description' => 'Manuelle Block-Verbindung zum vorherigen Eintrag (KEIN Tag-Link).'],
                'event_day_id'  => ['type' => 'integer', 'description' => 'Convenience: zieht datum aus events_event_days.id, wenn datum nicht gesetzt.'],
                'day_id'        => ['type' => 'integer', 'description' => 'Alias fuer event_day_id.'],
            ]),
            'required' => ['beschreibung'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) {
                return $event;
            }
            if (empty($arguments['beschreibung'])) {
                return ToolResult::error('VALIDATION_ERROR', 'beschreibung ist erforderlich.');
            }

            // Aliases zwischen Tag/Buchung/Englisch normalisieren.
            $aliasesApplied = $this->normalizeTimeFields($arguments, ['start' => 'von', 'end' => 'bis']);

            // Convenience: event_day_id|day_id → datum aus Tag uebernehmen, wenn datum leer.
            $dayIdInput = $arguments['event_day_id'] ?? ($arguments['day_id'] ?? null);
            if ($dayIdInput && empty($arguments['datum'])) {
                $linkedDay = EventDay::where('event_id', $event->id)->find((int) $dayIdInput);
                if ($linkedDay && $linkedDay->datum) {
                    $arguments['datum'] = $linkedDay->datum->format('Y-m-d');
                    $aliasesApplied[] = 'event_day_id→datum';
                }
            }

            $known = [
                'event_id', 'event_uuid', 'event_number',
                'beschreibung', 'datum', 'von', 'bis', 'raum', 'bemerkung', 'linked',
                'beginn', 'ende', 'start_time', 'end_time', 'start', 'end',
                'event_day_id', 'day_id',
            ];
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $maxSort = (int) ScheduleItem::where('event_id', $event->id)->max('sort_order');

            $item = ScheduleItem::create([
                'event_id'     => $event->id,
                'team_id'      => $event->team_id,
                'user_id'      => $context->user->id,
                'datum'        => $arguments['datum'] ?? null,
                'von'          => $arguments['von'] ?? null,
                'bis'          => $arguments['bis'] ?? null,
                'beschreibung' => $arguments['beschreibung'],
                'raum'         => $arguments['raum'] ?? null,
                'bemerkung'    => $arguments['bemerkung'] ?? null,
                'linked'       => (bool) ($arguments['linked'] ?? false),
                'sort_order'   => $maxSort + 1,
            ]);

            // Tag-Match (logisch, kein FK): finde EventDay mit gleichem datum.
            $matchedDayId = null;
            if ($item->datum) {
                $datumStr = is_string($item->datum) ? $item->datum : $item->datum->format('Y-m-d');
                $matchedDayId = EventDay::where('event_id', $event->id)
                    ->whereDate('datum', $datumStr)
                    ->value('id');
            }

            return ToolResult::success([
                'id'                    => $item->id,
                'uuid'                  => $item->uuid,
                'event_id'              => $event->id,
                'datum'                 => $item->datum,
                'von'                   => $item->von,
                'bis'                   => $item->bis,
                'beschreibung'          => $item->beschreibung,
                'raum'                  => $item->raum,
                'bemerkung'             => $item->bemerkung,
                'linked'                => (bool) $item->linked,
                'sort_order'            => $item->sort_order,
                'matched_event_day_id'  => $matchedDayId,
                'aliases_applied'       => $aliasesApplied,
                'ignored_fields'        => $ignored,
                'message'               => "Ablauf-Eintrag zu Event #{$event->event_number} hinzugefügt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'schedule', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
