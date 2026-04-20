<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Booking;

class GetBookingTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.booking.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/bookings/{id} - Details einer Raum-Buchung. Identifikation: booking_id ODER uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'booking_id' => ['type' => 'integer'],
                'uuid'       => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = Booking::query()->with('location');
            if (!empty($arguments['booking_id'])) {
                $query->where('id', (int) $arguments['booking_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'booking_id oder uuid ist erforderlich.');
            }

            $b = $query->first();
            if (!$b) {
                return ToolResult::error('BOOKING_NOT_FOUND', 'Die Buchung wurde nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $b->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Buchung.');
            }

            return ToolResult::success([
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
                'team_id'          => $b->team_id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Buchung: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'booking', 'get'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
