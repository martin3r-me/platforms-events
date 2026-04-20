<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ScheduleItem;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class CreateScheduleItemTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.schedule-items.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/schedule - Legt einen Ablaufplan-Eintrag an. Pflicht: event-Selector + beschreibung. Optional: datum, von, bis, raum, bemerkung, linked.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'beschreibung' => ['type' => 'string'],
                'datum'        => ['type' => 'string'],
                'von'          => ['type' => 'string'],
                'bis'          => ['type' => 'string'],
                'raum'         => ['type' => 'string'],
                'bemerkung'    => ['type' => 'string'],
                'linked'       => ['type' => 'boolean'],
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

            return ToolResult::success([
                'id'           => $item->id,
                'uuid'         => $item->uuid,
                'event_id'     => $event->id,
                'beschreibung' => $item->beschreibung,
                'sort_order'   => $item->sort_order,
                'message'      => "Ablauf-Eintrag zu Event #{$event->event_number} hinzugefügt.",
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
