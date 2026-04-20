<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\EventDay;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Listet die Tage eines Events.
 */
class ListEventDaysTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.days.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/days - Listet Event-Tage. Event via event_id ODER event_uuid ODER event_number. filters/sort/limit/offset optional.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            ['properties' => $this->eventSelectorSchema()]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) {
                return $event;
            }

            $query = $event->days();

            $this->applyStandardFilters($query, $arguments, ['label', 'datum', 'day_of_week', 'day_status', 'von', 'bis']);
            $this->applyStandardSearch($query, $arguments, ['label', 'day_of_week', 'day_status']);
            $this->applyStandardSort($query, $arguments, ['sort_order', 'datum', 'label', 'created_at'], 'sort_order', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $days = $query->get()->map(fn (EventDay $d) => [
                'id'          => $d->id,
                'uuid'        => $d->uuid,
                'event_id'    => $d->event_id,
                'label'       => $d->label,
                'datum'       => $d->datum?->toDateString(),
                'day_of_week' => $d->day_of_week,
                'von'         => $d->von,
                'bis'         => $d->bis,
                'pers_von'    => $d->pers_von,
                'pers_bis'    => $d->pers_bis,
                'day_status'  => $d->day_status,
                'color'       => $d->color,
                'sort_order'  => $d->sort_order,
            ])->toArray();

            return ToolResult::success([
                'days'     => $days,
                'count'    => count($days),
                'event_id' => $event->id,
                'message'  => count($days) . ' Tag(e) für Event #' . $event->event_number . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Tage: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'day', 'list'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
