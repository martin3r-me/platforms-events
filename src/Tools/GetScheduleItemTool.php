<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ScheduleItem;

class GetScheduleItemTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.schedule-item.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/schedule/{id} - Details zu einem Ablaufplan-Eintrag. '
            . 'Identifikation: schedule_id (Alias schedule_item_id) ODER uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'schedule_id'      => ['type' => 'integer'],
                'schedule_item_id' => ['type' => 'integer', 'description' => 'Alias fuer schedule_id.'],
                'uuid'             => ['type' => 'string'],
            ],
        ];
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

            $s = $query->first();
            if (!$s) {
                return ToolResult::error('SCHEDULE_NOT_FOUND', 'Der Ablauf-Eintrag wurde nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $s->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Eintrag.');
            }

            return ToolResult::success([
                'id'           => $s->id,
                'uuid'         => $s->uuid,
                'event_id'     => $s->event_id,
                'datum'        => $s->datum,
                'von'          => $s->von,
                'bis'          => $s->bis,
                'beschreibung' => $s->beschreibung,
                'raum'         => $s->raum,
                'bemerkung'    => $s->bemerkung,
                'linked'       => (bool) $s->linked,
                'sort_order'   => $s->sort_order,
                'team_id'      => $s->team_id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'schedule', 'get'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
