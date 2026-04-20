<?php

namespace Platform\Events\Tools;

use Carbon\Carbon;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Event;
use Platform\Events\Models\EventDay;

/**
 * Erstellt ein neues Event. Pflicht: name. Empfohlen: start_date + end_date.
 *
 * Generiert automatisch event_number (VA#YYYY-MMx) pro Team und – falls
 * start_date gesetzt ist – Event-Days für den Datumsbereich.
 */
class CreateEventTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.events.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events - Erstellt ein Event. Pflichtfeld: name. Optional: customer, group, location, start_date, end_date, status, '
            . 'organizer_contact, organizer_contact_onsite, organizer_for_whom, orderer_company, orderer_contact, orderer_via, '
            . 'invoice_to, invoice_contact, invoice_date_type, responsible, cost_center, cost_carrier, event_type, '
            . 'sign_left, sign_right, mr_data (object), follow_up_date, follow_up_note, delivery_supplier, delivery_contact, '
            . 'inquiry_date, inquiry_time, inquiry_note, potential, forwarded, forwarding_date, forwarding_time, '
            . 'auto_create_days (boolean, default true) – bei gesetztem start_date werden EventDays angelegt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name'       => ['type' => 'string',  'description' => 'Name der Veranstaltung (ERFORDERLICH).'],
                'team_id'    => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'customer'   => ['type' => 'string'],
                'group'      => ['type' => 'string'],
                'location'   => ['type' => 'string',  'description' => 'Ort (freitext, legacy).'],
                'start_date' => ['type' => 'string',  'description' => 'YYYY-MM-DD.'],
                'end_date'   => ['type' => 'string',  'description' => 'YYYY-MM-DD.'],
                'status'     => ['type' => 'string',  'description' => 'Option | Definitiv | Vertrag | Abgeschlossen | Storno | Warteliste | Tendenz'],
                'event_type' => ['type' => 'string'],

                'organizer_contact'        => ['type' => 'string'],
                'organizer_contact_onsite' => ['type' => 'string'],
                'organizer_for_whom'       => ['type' => 'string'],

                'orderer_company' => ['type' => 'string'],
                'orderer_contact' => ['type' => 'string'],
                'orderer_via'     => ['type' => 'string', 'description' => 'mail|phone|meeting|referral|other'],

                'invoice_to'        => ['type' => 'string'],
                'invoice_contact'   => ['type' => 'string'],
                'invoice_date_type' => ['type' => 'string'],

                'responsible'  => ['type' => 'string'],
                'cost_center'  => ['type' => 'string'],
                'cost_carrier' => ['type' => 'string'],

                'sign_left'  => ['type' => 'string'],
                'sign_right' => ['type' => 'string'],

                'mr_data' => ['type' => 'object', 'description' => 'Management-Report Werte als Key/Value-Map.'],

                'follow_up_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'follow_up_note' => ['type' => 'string'],

                'delivery_supplier' => ['type' => 'string'],
                'delivery_contact'  => ['type' => 'string'],

                'inquiry_date' => ['type' => 'string'],
                'inquiry_time' => ['type' => 'string'],
                'inquiry_note' => ['type' => 'string'],
                'potential'    => ['type' => 'string'],

                'forwarded'       => ['type' => 'boolean'],
                'forwarding_date' => ['type' => 'string'],
                'forwarding_time' => ['type' => 'string'],

                'auto_create_days' => ['type' => 'boolean', 'description' => 'Default true: EventDays aus start_date..end_date anlegen.'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }
            if (empty($arguments['name'])) {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
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

            $prefix = 'VA#' . now()->year . '-' . now()->format('m');
            $last = Event::where('team_id', $teamId)
                ->where('event_number', 'like', $prefix . '%')
                ->orderByRaw('LENGTH(event_number) DESC, event_number DESC')
                ->value('event_number');
            $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            $payload = [
                'team_id'            => $teamId,
                'user_id'            => $context->user->id,
                'event_number'       => $prefix . $next,
                'name'               => $arguments['name'],
                'organizer_for_whom' => $arguments['organizer_for_whom'] ?? $arguments['name'],
                'status'             => $arguments['status'] ?? 'Option',
                'status_changed_at'  => now(),
            ];

            foreach ([
                'customer', 'group', 'location', 'start_date', 'end_date', 'event_type',
                'organizer_contact', 'organizer_contact_onsite',
                'orderer_company', 'orderer_contact', 'orderer_via',
                'invoice_to', 'invoice_contact', 'invoice_date_type',
                'responsible', 'cost_center', 'cost_carrier',
                'sign_left', 'sign_right',
                'follow_up_date', 'follow_up_note',
                'delivery_supplier', 'delivery_contact',
                'inquiry_date', 'inquiry_time', 'inquiry_note', 'potential',
                'forwarding_date', 'forwarding_time',
            ] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $payload[$f] = $arguments[$f];
                }
            }
            if (array_key_exists('forwarded', $arguments)) {
                $payload['forwarded'] = (bool) $arguments['forwarded'];
            }
            if (array_key_exists('mr_data', $arguments) && is_array($arguments['mr_data'])) {
                $payload['mr_data'] = $arguments['mr_data'];
            }

            $event = Event::create($payload);

            // EventDays automatisch anlegen
            $autoDays = $arguments['auto_create_days'] ?? true;
            if ($autoDays && !empty($payload['start_date'])) {
                try {
                    $start = Carbon::parse($payload['start_date']);
                    $end = !empty($payload['end_date']) ? Carbon::parse($payload['end_date']) : $start->copy();
                    $weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
                    $sort = 0;
                    $maxDays = min((int) $start->diffInDays($end) + 1, 365);

                    for ($dt = $start->copy(); $dt->lte($end) && $sort < $maxDays; $dt->addDay()) {
                        EventDay::create([
                            'team_id'     => $teamId,
                            'user_id'     => $context->user->id,
                            'event_id'    => $event->id,
                            'label'       => $dt->format('d.m.Y'),
                            'datum'       => $dt->format('Y-m-d'),
                            'day_of_week' => $weekdays[$dt->dayOfWeek],
                            'day_status'  => $payload['status'],
                            'color'       => '#6366f1',
                            'sort_order'  => $sort++,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Datum ungültig – Event bleibt ohne Tage
                }
            }

            $event->refresh()->load('days');

            return ToolResult::success([
                'id'           => $event->id,
                'uuid'         => $event->uuid,
                'slug'         => $event->slug,
                'event_number' => $event->event_number,
                'name'         => $event->name,
                'status'       => $event->status,
                'start_date'   => $event->start_date?->toDateString(),
                'end_date'     => $event->end_date?->toDateString(),
                'team_id'      => $event->team_id,
                'days_created' => $event->days->count(),
                'message'      => "Event '{$event->name}' erfolgreich erstellt (#{$event->event_number}).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Events: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'event', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
