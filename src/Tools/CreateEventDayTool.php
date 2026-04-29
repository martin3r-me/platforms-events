<?php

namespace Platform\Events\Tools;

use Carbon\Carbon;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class CreateEventDayTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;
    use CollectsValidationErrors;

    public function getName(): string
    {
        return 'events.days.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/days - Legt einen Event-Tag an. '
            . 'Pflicht: event-Selector + label + datum (YYYY-MM-DD). '
            . 'Optional: day_type ("Veranstaltungstag" [Default] | "Aufbautag" | "Abbautag" | "Ruesttag"; weitere via Settings → Tages-Typen), '
            . 'von/bis (HH:MM), pers_von/pers_bis (Personenzahlen), '
            . 'day_status ("Option" [Default] | "Definitiv" | "Vertrag" ...), color (#RRGGBB, Default #6366f1). '
            . 'day_of_week wird aus datum abgeleitet, wenn nicht uebergeben (So..Sa). '
            . 'sort_order wird automatisch (max+1) gesetzt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'label'       => ['type' => 'string',  'description' => 'Anzeigename des Tages (z.B. "Tag 1" oder "20.03.2026").'],
                'day_type'    => ['type' => 'string',  'description' => 'Veranstaltungstag|Aufbautag|Abbautag|Rüsttag. Default Veranstaltungstag.'],
                'datum'       => ['type' => 'string',  'description' => 'YYYY-MM-DD.'],
                'day_of_week' => ['type' => 'string'],
                'von'         => ['type' => 'string'],
                'bis'         => ['type' => 'string'],
                'pers_von'    => ['type' => 'string'],
                'pers_bis'    => ['type' => 'string'],
                'day_status'  => ['type' => 'string', 'description' => 'Option|Definitiv|Vertrag|...'],
                'color'       => ['type' => 'string', 'description' => '#RRGGBB.'],
            ]),
            'required' => ['label', 'datum'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) {
                return $event;
            }

            $errors = [];
            if (empty($arguments['label'])) {
                $errors[] = $this->validationError('label', 'label ist erforderlich (z.B. "Tag 1" oder "20.03.2026").');
            }
            if (empty($arguments['datum'])) {
                $errors[] = $this->validationError('datum', 'datum ist erforderlich (YYYY-MM-DD).');
            }
            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }

            $weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
            $dow = $arguments['day_of_week'] ?? null;
            if (empty($dow)) {
                try {
                    $dow = $weekdays[Carbon::parse($arguments['datum'])->dayOfWeek];
                } catch (\Throwable $e) {
                    $dow = null;
                }
            }

            $maxSort = (int) EventDay::where('event_id', $event->id)->max('sort_order');

            $day = EventDay::create([
                'event_id'    => $event->id,
                'team_id'     => $event->team_id,
                'user_id'     => $context->user->id,
                'label'       => $arguments['label'],
                'day_type'    => $arguments['day_type'] ?? 'Veranstaltungstag',
                'datum'       => $arguments['datum'],
                'day_of_week' => $dow,
                'von'         => $arguments['von'] ?? null,
                'bis'         => $arguments['bis'] ?? null,
                'pers_von'    => $arguments['pers_von'] ?? null,
                'pers_bis'    => $arguments['pers_bis'] ?? null,
                'day_status'  => $arguments['day_status'] ?? 'Option',
                'color'       => $arguments['color'] ?? '#6366f1',
                'sort_order'  => $maxSort + 1,
            ]);

            return ToolResult::success([
                'id'         => $day->id,
                'uuid'       => $day->uuid,
                'event_id'   => $event->id,
                'label'      => $day->label,
                'datum'      => $day->datum?->toDateString(),
                'sort_order' => $day->sort_order,
                'message'    => "Tag '{$day->label}' zu Event #{$event->event_number} hinzugefügt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen des Tages: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'day', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
