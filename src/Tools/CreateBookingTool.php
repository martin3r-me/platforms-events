<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Booking;
use Platform\Events\Tools\Concerns\ResolvesEvent;
use Platform\Locations\Models\Location;

class CreateBookingTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.bookings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/bookings - Legt eine Raum-Buchung an. Event-Selector plus (location_id ODER raum). '
            . 'Optional: datum, beginn, ende, pers, bestuhlung, optionsrang, absprache. location_id bevorzugt, raum ist Legacy-Fallback.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'location_id' => ['type' => 'integer', 'description' => 'FK auf locations_locations.id – bevorzugt gegenueber raum.'],
                'raum'        => ['type' => 'string',  'description' => 'Legacy-Fallback-Kürzel.'],
                'datum'       => ['type' => 'string',  'description' => 'YYYY-MM-DD oder freitext.'],
                'beginn'      => ['type' => 'string',  'description' => 'HH:MM.'],
                'ende'        => ['type' => 'string'],
                'pers'        => ['type' => 'string'],
                'bestuhlung'  => ['type' => 'string'],
                'optionsrang' => ['type' => 'string',  'description' => 'z.B. "1. Option", "Definitiv", "Vertrag"'],
                'absprache'   => ['type' => 'string'],
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

            $locationId = !empty($arguments['location_id']) ? (int) $arguments['location_id'] : null;
            $raum = $arguments['raum'] ?? null;

            if (!$locationId && empty($raum)) {
                return ToolResult::error('VALIDATION_ERROR', 'location_id oder raum ist erforderlich.');
            }

            if ($locationId) {
                $loc = Location::find($locationId);
                if (!$loc) {
                    return ToolResult::error('LOCATION_NOT_FOUND', "Location-ID {$locationId} existiert nicht.");
                }
                if ($loc->team_id !== $event->team_id) {
                    return ToolResult::error('VALIDATION_ERROR', 'Die Location gehört einem anderen Team als das Event.');
                }
            }

            $maxSort = (int) Booking::where('event_id', $event->id)->max('sort_order');

            $booking = Booking::create([
                'event_id'    => $event->id,
                'team_id'     => $event->team_id,
                'user_id'     => $context->user->id,
                'location_id' => $locationId,
                'raum'        => $raum,
                'datum'       => $arguments['datum'] ?? null,
                'beginn'      => $arguments['beginn'] ?? null,
                'ende'        => $arguments['ende'] ?? null,
                'pers'        => $arguments['pers'] ?? null,
                'bestuhlung'  => $arguments['bestuhlung'] ?? null,
                'optionsrang' => $arguments['optionsrang'] ?? '1. Option',
                'absprache'   => $arguments['absprache'] ?? null,
                'sort_order'  => $maxSort + 1,
            ]);

            return ToolResult::success([
                'id'          => $booking->id,
                'uuid'        => $booking->uuid,
                'event_id'    => $event->id,
                'location_id' => $booking->location_id,
                'raum'        => $booking->raum,
                'optionsrang' => $booking->optionsrang,
                'message'     => "Buchung angelegt für Event #{$event->event_number}.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der Buchung: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'booking', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
