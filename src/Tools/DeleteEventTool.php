<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Event;

/**
 * Löscht ein Event (Soft Delete). Tage/Buchungen/Ablauf/Notizen bleiben als Soft-Deleted Datensätze erhalten.
 */
class DeleteEventTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.events.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/{id} - Löscht ein Event (Soft Delete). Identifikation: event_id ODER uuid ODER event_number.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'event_id'     => ['type' => 'integer'],
                'uuid'         => ['type' => 'string'],
                'event_number' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = Event::query();
            if (!empty($arguments['event_id'])) {
                $query->where('id', (int) $arguments['event_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } elseif (!empty($arguments['event_number'])) {
                $raw = (string) $arguments['event_number'];
                $query->where(function ($q) use ($raw) {
                    $q->where('event_number', $raw)
                      ->orWhere('event_number', preg_replace('/^(VA)(\d)/', '$1#$2', $raw));
                });
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'event_id, uuid oder event_number ist erforderlich.');
            }

            $event = $query->first();
            if (!$event) {
                return ToolResult::error('EVENT_NOT_FOUND', 'Das angegebene Event wurde nicht gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', $event->team_id)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Event.');
            }

            $name = $event->name;
            $id   = $event->id;
            $uuid = $event->uuid;
            $num  = $event->event_number;
            $event->delete();

            return ToolResult::success([
                'id'           => $id,
                'uuid'         => $uuid,
                'event_number' => $num,
                'name'         => $name,
                'message'      => "Event '{$name}' (#{$num}) erfolgreich gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Events: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'event', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['deletes'],
        ];
    }
}
