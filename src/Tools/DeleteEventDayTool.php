<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;

class DeleteEventDayTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.days.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/days/{id} - Löscht einen Event-Tag (Soft Delete). Identifikation: day_id ODER uuid.';
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

            $label = $day->label;
            $id    = $day->id;
            $uuid  = $day->uuid;
            $day->delete();

            return ToolResult::success([
                'id'      => $id,
                'uuid'    => $uuid,
                'label'   => $label,
                'message' => "Tag '{$label}' gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Tages: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'day', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['deletes'],
        ];
    }
}
