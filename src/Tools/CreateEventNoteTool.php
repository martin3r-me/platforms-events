<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventNote;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class CreateEventNoteTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;

    protected const VALID_TYPES = ['liefertext', 'absprache', 'vereinbarung'];

    public function getName(): string
    {
        return 'events.notes.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/notes - Legt eine Notiz an. Pflicht: event-Selector, type (liefertext|absprache|vereinbarung), text. Optional: user_name (Default: aktueller User).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'type'      => ['type' => 'string', 'enum' => self::VALID_TYPES],
                'text'      => ['type' => 'string'],
                'user_name' => ['type' => 'string'],
            ]),
            'required' => ['type', 'text'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) {
                return $event;
            }
            if (empty($arguments['type']) || !in_array($arguments['type'], self::VALID_TYPES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'type muss einer von: ' . implode(', ', self::VALID_TYPES));
            }
            if (empty($arguments['text'])) {
                return ToolResult::error('VALIDATION_ERROR', 'text ist erforderlich.');
            }

            $note = EventNote::create([
                'event_id'  => $event->id,
                'team_id'   => $event->team_id,
                'user_id'   => $context->user->id,
                'type'      => $arguments['type'],
                'text'      => $arguments['text'],
                'user_name' => $arguments['user_name'] ?? ($context->user->name ?? 'Benutzer'),
            ]);

            return ToolResult::success([
                'id'       => $note->id,
                'uuid'     => $note->uuid,
                'event_id' => $event->id,
                'type'     => $note->type,
                'message'  => "Notiz zu Event #{$event->event_number} angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der Notiz: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'note', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
