<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\EventNote;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class ListEventNotesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.notes.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/notes - Listet Notizen eines Events. Event-Selector plus optionalem type-Filter (liefertext|absprache|vereinbarung).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => array_merge($this->eventSelectorSchema(), [
                    'type' => ['type' => 'string', 'description' => 'Optional: liefertext | absprache | vereinbarung'],
                ]),
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) {
                return $event;
            }

            $query = $event->notes();

            if (!empty($arguments['type'])) {
                $query->where('type', $arguments['type']);
            }

            $this->applyStandardFilters($query, $arguments, ['type', 'user_name']);
            $this->applyStandardSearch($query, $arguments, ['text', 'user_name']);
            $this->applyStandardSort($query, $arguments, ['created_at', 'type'], 'created_at', 'desc');
            $this->applyStandardPagination($query, $arguments);

            $notes = $query->get()->map(fn (EventNote $n) => [
                'id'         => $n->id,
                'uuid'       => $n->uuid,
                'event_id'   => $n->event_id,
                'type'       => $n->type,
                'text'       => $n->text,
                'user_name'  => $n->user_name,
                'created_at' => $n->created_at?->toIso8601String(),
                'updated_at' => $n->updated_at?->toIso8601String(),
            ])->toArray();

            return ToolResult::success([
                'notes'    => $notes,
                'count'    => count($notes),
                'event_id' => $event->id,
                'message'  => count($notes) . ' Notiz(en) für Event #' . $event->event_number . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Notizen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'note', 'list'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
