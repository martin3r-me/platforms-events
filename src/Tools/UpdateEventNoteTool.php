<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventNote;

class UpdateEventNoteTool implements ToolContract, ToolMetadataContract
{
    protected const VALID_TYPES = ['liefertext', 'absprache', 'vereinbarung'];

    public function getName(): string
    {
        return 'events.notes.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/notes/{id} - Aktualisiert eine Notiz. Identifikation: note_id ODER uuid. Felder: type, text, user_name.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'note_id'   => ['type' => 'integer'],
                'uuid'      => ['type' => 'string'],
                'type'      => ['type' => 'string', 'enum' => self::VALID_TYPES],
                'text'      => ['type' => 'string'],
                'user_name' => ['type' => 'string'],
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

            $update = [];
            if (array_key_exists('type', $arguments)) {
                if (!in_array($arguments['type'], self::VALID_TYPES, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'type muss einer von: ' . implode(', ', self::VALID_TYPES));
                }
                $update['type'] = $arguments['type'];
            }
            if (array_key_exists('text', $arguments)) {
                $update['text'] = (string) $arguments['text'];
            }
            if (array_key_exists('user_name', $arguments)) {
                $update['user_name'] = (string) $arguments['user_name'];
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $note->update($update);

            return ToolResult::success([
                'id'       => $note->id,
                'uuid'     => $note->uuid,
                'event_id' => $note->event_id,
                'message'  => 'Notiz aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'note', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
