<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Booking;
use Platform\Locations\Models\Location;

class UpdateBookingTool implements ToolContract, ToolMetadataContract
{
    protected const STRING_FIELDS = [
        'raum', 'datum', 'beginn', 'ende', 'pers',
        'bestuhlung', 'optionsrang', 'absprache',
    ];

    public function getName(): string
    {
        return 'events.bookings.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/bookings/{id} - Aktualisiert eine Buchung. Identifikation: booking_id ODER uuid. '
            . 'Felder: location_id, raum, datum, beginn, ende, pers, bestuhlung, optionsrang, absprache, sort_order.';
    }

    public function getSchema(): array
    {
        $props = [
            'booking_id'  => ['type' => 'integer'],
            'uuid'        => ['type' => 'string'],
            'location_id' => ['type' => 'integer', 'description' => 'null setzen, um auf raum-Fallback zurueck zu wechseln.'],
            'sort_order'  => ['type' => 'integer'],
        ];
        foreach (self::STRING_FIELDS as $f) {
            $props[$f] = ['type' => 'string'];
        }
        return ['type' => 'object', 'properties' => $props];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = Booking::query();
            if (!empty($arguments['booking_id'])) {
                $query->where('id', (int) $arguments['booking_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'booking_id oder uuid ist erforderlich.');
            }

            $booking = $query->first();
            if (!$booking) {
                return ToolResult::error('BOOKING_NOT_FOUND', 'Die Buchung wurde nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $booking->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Buchung.');
            }

            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f];
                }
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int) $arguments['sort_order'];
            }
            if (array_key_exists('location_id', $arguments)) {
                $locId = $arguments['location_id'];
                if ($locId !== null && $locId !== '') {
                    $loc = Location::find((int) $locId);
                    if (!$loc) {
                        return ToolResult::error('LOCATION_NOT_FOUND', "Location-ID {$locId} existiert nicht.");
                    }
                    if ($loc->team_id !== $booking->team_id) {
                        return ToolResult::error('VALIDATION_ERROR', 'Die Location gehört einem anderen Team.');
                    }
                    $update['location_id'] = (int) $locId;
                } else {
                    $update['location_id'] = null;
                }
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $booking->update($update);

            return ToolResult::success([
                'id'          => $booking->id,
                'uuid'        => $booking->uuid,
                'event_id'    => $booking->event_id,
                'location_id' => $booking->location_id,
                'message'     => 'Buchung aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Buchung: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'booking', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
