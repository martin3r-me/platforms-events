<?php

namespace Platform\Events\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Event;

/**
 * Helfer, um ein Parent-Event anhand event_id/uuid/event_number zu finden
 * und die Team-Zugriffsberechtigung zu prüfen.
 */
trait ResolvesEvent
{
    /**
     * @return Event|ToolResult Event oder Error-Result.
     */
    protected function resolveEvent(array $arguments, ToolContext $context): Event|ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
        }

        $query = Event::query();
        if (!empty($arguments['event_id'])) {
            $query->where('id', (int) $arguments['event_id']);
        } elseif (!empty($arguments['event_uuid'])) {
            $query->where('uuid', $arguments['event_uuid']);
        } elseif (!empty($arguments['event_number'])) {
            $raw = (string) $arguments['event_number'];
            $query->where(function ($q) use ($raw) {
                $q->where('event_number', $raw)
                  ->orWhere('event_number', preg_replace('/^(VA)(\d)/', '$1#$2', $raw));
            });
        } else {
            return ToolResult::error('VALIDATION_ERROR', 'event_id, event_uuid oder event_number ist erforderlich.');
        }

        $event = $query->first();
        if (!$event) {
            return ToolResult::error('EVENT_NOT_FOUND', 'Das angegebene Event wurde nicht gefunden.');
        }

        $hasAccess = $context->user->teams()->where('teams.id', $event->team_id)->exists();
        if (!$hasAccess) {
            return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Event.');
        }

        return $event;
    }

    protected function eventSelectorSchema(): array
    {
        return [
            'event_id'     => ['type' => 'integer', 'description' => 'ID des Events.'],
            'event_uuid'   => ['type' => 'string',  'description' => 'UUID des Events.'],
            'event_number' => ['type' => 'string',  'description' => 'VA-Nummer (z.B. "VA#2026-031" oder "VA2026-031").'],
        ];
    }
}
