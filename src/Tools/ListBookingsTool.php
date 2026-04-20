<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\Booking;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class ListBookingsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.bookings.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/bookings - Listet Raum-Buchungen eines Events. Event via event_id/event_uuid/event_number. '
            . 'Optional location_id Filter. filters/sort/limit/offset wie Standard.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => array_merge($this->eventSelectorSchema(), [
                    'location_id' => ['type' => 'integer', 'description' => 'Optional: nur Buchungen dieser Location.'],
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

            $query = $event->bookings()->with('location');

            if (!empty($arguments['location_id'])) {
                $query->where('location_id', (int) $arguments['location_id']);
            }

            $this->applyStandardFilters($query, $arguments, ['raum', 'datum', 'optionsrang', 'bestuhlung', 'pers']);
            $this->applyStandardSearch($query, $arguments, ['raum', 'absprache', 'bestuhlung']);
            $this->applyStandardSort($query, $arguments, ['sort_order', 'datum', 'created_at'], 'sort_order', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $bookings = $query->get()->map(fn (Booking $b) => [
                'id'               => $b->id,
                'uuid'             => $b->uuid,
                'event_id'         => $b->event_id,
                'location_id'      => $b->location_id,
                'location_kuerzel' => $b->location?->kuerzel,
                'location_name'    => $b->location?->name,
                'raum'             => $b->raum,
                'datum'            => $b->datum,
                'beginn'           => $b->beginn,
                'ende'             => $b->ende,
                'pers'             => $b->pers,
                'bestuhlung'       => $b->bestuhlung,
                'optionsrang'      => $b->optionsrang,
                'absprache'        => $b->absprache,
                'sort_order'       => $b->sort_order,
            ])->toArray();

            return ToolResult::success([
                'bookings' => $bookings,
                'count'    => count($bookings),
                'event_id' => $event->id,
                'message'  => count($bookings) . ' Buchung(en) für Event #' . $event->event_number . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Buchungen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'booking', 'list'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
