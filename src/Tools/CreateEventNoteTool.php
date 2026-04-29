<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventNote;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class CreateEventNoteTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;
    use CollectsValidationErrors;

    protected const VALID_TYPES = ['liefertext', 'absprache', 'vereinbarung'];

    public function getName(): string
    {
        return 'events.notes.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/notes - Legt eine Notiz an einem Event an. '
            . 'Pflicht: event-Selector + type ("liefertext" | "absprache" | "vereinbarung") + text (Markdown erlaubt). '
            . 'Optional: user_name (Default: aktueller User-Name; manuell ueberschreibbar fuer Importe). '
            . 'Notizen sind chronologisch und unveraenderlich (Audit-Spur). Update via events.notes.PATCH.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'type'      => ['type' => 'string', 'enum' => self::VALID_TYPES, 'description' => 'Notiz-Kategorie. liefertext = Hinweis fuer Lieferung; absprache = Kunden-Absprache; vereinbarung = formaler Vermerk.'],
                'text'      => ['type' => 'string', 'description' => 'Inhalt der Notiz (Markdown erlaubt).'],
                'user_name' => ['type' => 'string', 'description' => 'Default: Name des aktuellen Users.'],
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

            $errors = [];
            if (empty($arguments['type'])) {
                $errors[] = $this->validationError('type', 'type ist erforderlich (' . implode('|', self::VALID_TYPES) . ').');
            } elseif (!in_array($arguments['type'], self::VALID_TYPES, true)) {
                $errors[] = $this->validationError('type', 'type muss einer von: ' . implode(', ', self::VALID_TYPES));
            }
            if (empty($arguments['text'])) {
                $errors[] = $this->validationError('text', 'text ist erforderlich.');
            }
            if (!empty($errors)) {
                return $this->validationFailure($errors);
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
