<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Booking;

class DeleteBookingTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.bookings.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/bookings/{id} - Löscht eine Raum-Buchung (Soft Delete). Identifikation: booking_id ODER uuid.';
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

            $query = Booking::query();
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

            $id = $b->id; $uuid = $b->uuid;
            $b->delete();

            return ToolResult::success([
                'id'      => $id,
                'uuid'    => $uuid,
                'message' => 'Buchung gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Buchung: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'booking', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['deletes'],
        ];
    }
}
