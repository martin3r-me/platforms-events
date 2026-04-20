<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\ScheduleItem;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class ListScheduleItemsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.schedule-items.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/schedule - Listet Ablaufplan-Eintraege eines Events. Event via event_id/event_uuid/event_number.';
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

            $query = $event->scheduleItems();

            $this->applyStandardFilters($query, $arguments, ['datum', 'raum', 'linked']);
            $this->applyStandardSearch($query, $arguments, ['beschreibung', 'raum', 'bemerkung']);
            $this->applyStandardSort($query, $arguments, ['sort_order', 'datum', 'von', 'created_at'], 'sort_order', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $items = $query->get()->map(fn (ScheduleItem $s) => [
                'id'           => $s->id,
                'uuid'         => $s->uuid,
                'event_id'     => $s->event_id,
                'datum'        => $s->datum,
                'von'          => $s->von,
                'bis'          => $s->bis,
                'beschreibung' => $s->beschreibung,
                'raum'         => $s->raum,
                'bemerkung'    => $s->bemerkung,
                'linked'       => (bool) $s->linked,
                'sort_order'   => $s->sort_order,
            ])->toArray();

            return ToolResult::success([
                'schedule' => $items,
                'count'    => count($items),
                'event_id' => $event->id,
                'message'  => count($items) . ' Ablauf-Eintrag/Eintraege für Event #' . $event->event_number . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Ablaufplans: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'schedule', 'list'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
