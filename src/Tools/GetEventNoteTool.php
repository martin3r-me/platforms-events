<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventNote;

class GetEventNoteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.note.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/notes/{id} - Details zu einer Notiz. Identifikation: note_id ODER uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'note_id' => ['type' => 'integer'],
                'uuid'    => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = EventNote::query();
            if (!empty($arguments['note_id'])) {
                $query->where('id', (int) $arguments['note_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'note_id oder uuid ist erforderlich.');
            }

            $n = $query->first();
            if (!$n) {
                return ToolResult::error('NOTE_NOT_FOUND', 'Notiz nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $n->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            return ToolResult::success([
                'id'         => $n->id,
                'uuid'       => $n->uuid,
                'event_id'   => $n->event_id,
                'type'       => $n->type,
                'text'       => $n->text,
                'user_name'  => $n->user_name,
                'team_id'    => $n->team_id,
                'created_at' => $n->created_at?->toIso8601String(),
                'updated_at' => $n->updated_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Notiz: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'note', 'get'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
