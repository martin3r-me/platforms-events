<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Quote;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Listet alle Angebote eines Events (sortiert: aktuelle Version zuerst).
 */
class ListQuotesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.quotes.GET.list';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/quotes - Listet alle Angebote eines Events. '
            . 'Filter: only_current (boolean, default false) – nur is_current.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'only_current' => ['type' => 'boolean', 'description' => 'Nur is_current=true zurueckgeben. Default false.'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) {
                return $event;
            }

            $query = Quote::where('event_id', $event->id)->orderByDesc('version');
            if (!empty($arguments['only_current'])) {
                $query->where('is_current', true);
            }

            $quotes = $query->get()->map(fn ($q) => [
                'id'                 => $q->id,
                'uuid'               => $q->uuid,
                'version'            => $q->version,
                'parent_id'          => $q->parent_id,
                'is_current'         => (bool) $q->is_current,
                'status'             => $q->status,
                'valid_until'        => $q->valid_until?->toDateString(),
                'sent_at'            => $q->sent_at?->toIso8601String(),
                'responded_at'       => $q->responded_at?->toIso8601String(),
                'attach_floor_plans' => $q->attach_floor_plans,
                'approval_status'    => $q->approval_status,
                'view_count'         => (int) ($q->view_count ?? 0),
                'created_at'         => $q->created_at?->toIso8601String(),
            ])->all();

            return ToolResult::success([
                'event_id'     => $event->id,
                'event_number' => $event->event_number,
                'count'        => count($quotes),
                'quotes'       => $quotes,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Listen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'quote', 'list'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
