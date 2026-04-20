<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;

class GetEventDayTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.day.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/days/{id} - Details zu einem Event-Tag. Identifikation: day_id ODER uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'day_id' => ['type' => 'integer'],
                'uuid'   => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = EventDay::query();
            if (!empty($arguments['day_id'])) {
                $query->where('id', (int) $arguments['day_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'day_id oder uuid ist erforderlich.');
            }

            $day = $query->first();
            if (!$day) {
                return ToolResult::error('DAY_NOT_FOUND', 'Der Event-Tag wurde nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $day->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Tag.');
            }

            return ToolResult::success([
                'id'          => $day->id,
                'uuid'        => $day->uuid,
                'event_id'    => $day->event_id,
                'label'       => $day->label,
                'datum'       => $day->datum?->toDateString(),
                'day_of_week' => $day->day_of_week,
                'von'         => $day->von,
                'bis'         => $day->bis,
                'pers_von'    => $day->pers_von,
                'pers_bis'    => $day->pers_bis,
                'day_status'  => $day->day_status,
                'color'       => $day->color,
                'sort_order'  => $day->sort_order,
                'team_id'     => $day->team_id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Tages: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'day', 'get'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
