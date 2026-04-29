<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\Event;

/**
 * Listet Events (Veranstaltungen) des aktuellen Teams mit optionalen Filtern.
 */
class ListEventsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'events.events.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events?team_id=&status=&search=&from=&to=[...] - Listet Events des aktuellen Teams. '
            . 'REST-Parameter: team_id (optional, sonst aktuelles Team). status (optional, z.B. "Option"/"Definitiv"/"Vertrag"). '
            . 'search (optional, sucht in name/customer/event_number). from/to (optional, YYYY-MM-DD) für Zeitraum-Filter (überlappende Events). '
            . 'responsible (optional), group (optional). filters, sort, limit, offset (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id'     => ['type' => 'integer',  'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                    'status'      => ['type' => 'string',   'description' => 'Optional: Filter nach Status.'],
                    'responsible' => ['type' => 'string',   'description' => 'Optional: Filter nach Verantwortlichem.'],
                    'group'       => ['type' => 'string',   'description' => 'Optional: Filter nach Gruppe.'],
                    'from'        => ['type' => 'string',   'description' => 'Optional: Start-Datum (YYYY-MM-DD), Events die sich mit dem Zeitraum überlappen.'],
                    'to'          => ['type' => 'string',   'description' => 'Optional: End-Datum (YYYY-MM-DD).'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? null;
            if ($teamId === 0 || $teamId === '0') {
                $teamId = null;
            }
            if ($teamId === null) {
                $teamId = $context->team?->id;
            }
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', "Du hast keinen Zugriff auf Team-ID {$teamId}.");
            }

            $query = Event::query()->where('team_id', $teamId);

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }
            if (!empty($arguments['responsible'])) {
                $query->where('responsible', $arguments['responsible']);
            }
            if (!empty($arguments['group'])) {
                $query->where('group', $arguments['group']);
            }

            $from = $arguments['from'] ?? null;
            $to   = $arguments['to'] ?? null;
            if ($from || $to) {
                $query->where(function ($q) use ($from, $to) {
                    if ($to) {
                        $q->where(function ($q2) use ($to) {
                            $q2->where('start_date', '<=', $to)->orWhereNull('start_date');
                        });
                    }
                    if ($from) {
                        $q->where(function ($q2) use ($from) {
                            $q2->where('end_date', '>=', $from)->orWhereNull('end_date');
                        });
                    }
                });
            }

            $this->applyStandardFilters($query, $arguments, [
                'name', 'customer', 'group', 'location', 'status', 'event_type',
                'responsible', 'cost_center', 'cost_carrier', 'start_date', 'end_date',
                'follow_up_date', 'inquiry_date', 'forwarding_date', 'potential',
                'created_at', 'updated_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['name', 'customer', 'event_number', 'group', 'location']);
            $this->applyStandardSort($query, $arguments, [
                'start_date', 'end_date', 'name', 'event_number', 'status',
                'created_at', 'updated_at', 'status_changed_at',
            ], 'start_date', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $events = $query->get()->map(fn(Event $e) => $this->serialize($e))->toArray();

            return ToolResult::success([
                'events'  => $events,
                'count'   => count($events),
                'team_id' => $teamId,
                'message' => count($events) . ' Event(s) gefunden (Team-ID: ' . $teamId . ').',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Events: ' . $e->getMessage());
        }
    }

    use \Platform\Events\Tools\Concerns\HydratesEventReferences;

    protected function serialize(Event $e): array
    {
        $row = [
            'id'                => $e->id,
            'uuid'              => $e->uuid,
            'slug'              => $e->slug,
            'event_number'      => $e->event_number,
            'name'              => $e->name,
            'customer'          => $e->customer,
            'group'             => $e->group,
            'location'          => $e->location, // Legacy-Freitext
            'start_date'        => $e->start_date?->toDateString(),
            'end_date'          => $e->end_date?->toDateString(),
            'status'            => $e->status,
            'status_changed_at' => $e->status_changed_at?->toIso8601String(),
            'responsible'       => $e->responsible,
            'event_type'        => $e->event_type,
            'team_id'           => $e->team_id,
            'created_at'        => $e->created_at?->toIso8601String(),
            // Roh-FK (fuer Updates) + hydratisierte Customer-Company.
            'crm_company_id'    => $e->crm_company_id,
            'customer_company'  => $this->hydrateCrmCompany($e->crm_company_id),
        ];
        return $row;
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'event', 'list'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
