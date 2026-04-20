<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ScheduleItem;

class DeleteScheduleItemTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.schedule-items.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/schedule/{id} - Löscht einen Ablauf-Eintrag (Soft Delete). Identifikation: schedule_id ODER uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'schedule_id' => ['type' => 'integer'],
                'uuid'        => ['type' => 'string'],
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
            if (!empty($arguments['schedule_id'])) {
                $query->where('id', (int) $arguments['schedule_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'schedule_id oder uuid ist erforderlich.');
            }

            $item = $query->first();
            if (!$item) {
                return ToolResult::error('SCHEDULE_NOT_FOUND', 'Ablauf-Eintrag nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $item->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            $id = $item->id; $uuid = $item->uuid;
            $item->delete();

            return ToolResult::success([
                'id'      => $id,
                'uuid'    => $uuid,
                'message' => 'Ablauf-Eintrag gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'schedule', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['deletes'],
        ];
    }
}
