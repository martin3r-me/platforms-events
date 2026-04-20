<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventNote;

class DeleteEventNoteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.notes.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/notes/{id} - Löscht eine Notiz (Soft Delete). Identifikation: note_id ODER uuid.';
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

            $note = $query->first();
            if (!$note) {
                return ToolResult::error('NOTE_NOT_FOUND', 'Notiz nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $note->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            $id = $note->id; $uuid = $note->uuid;
            $note->delete();

            return ToolResult::success([
                'id'      => $id,
                'uuid'    => $uuid,
                'message' => 'Notiz gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'note', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['deletes'],
        ];
    }
}
