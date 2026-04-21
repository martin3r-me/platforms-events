<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\PickList;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class ListPickListsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.pick-lists.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/pick-lists - Listet Packlisten eines Events.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            ['properties' => array_merge($this->eventSelectorSchema(), [
                'status' => ['type' => 'string', 'description' => 'open | in_progress | packed | loaded'],
            ])]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) return $event;

            $query = PickList::where('event_id', $event->id)->withCount('items');
            if (!empty($arguments['status'])) $query->where('status', $arguments['status']);
            $this->applyStandardFilters($query, $arguments, ['status', 'title']);
            $this->applyStandardSort($query, $arguments, ['created_at'], 'created_at', 'desc');
            $this->applyStandardPagination($query, $arguments);

            $lists = $query->get()->map(fn (PickList $pl) => [
                'id' => $pl->id, 'uuid' => $pl->uuid, 'title' => $pl->title,
                'status' => $pl->status, 'token' => $pl->token,
                'items_count' => $pl->items_count ?? 0,
                'created_at' => $pl->created_at?->format('Y-m-d H:i'),
                'created_by' => $pl->created_by,
            ])->toArray();

            return ToolResult::success([
                'pick_lists' => $lists, 'count' => count($lists), 'event_id' => $event->id,
                'message' => count($lists) . ' Packliste(n).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category' => 'query', 'tags' => ['events', 'picklist', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true];
    }
}
