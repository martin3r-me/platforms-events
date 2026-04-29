<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Services\EventCloner;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Klont ein bestehendes Event (Stamm + Days + Bookings + ScheduleItems,
 * optional EventNotes) mit Override-Feldern. Quotes/Orders werden bewusst
 * nicht mitkopiert – ein neues Event bekommt typischerweise ein neues Angebot.
 */
class CloneEventTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.events.CLONE';
    }

    public function getDescription(): string
    {
        return 'POST /events/clone - Klont ein Event als neue Veranstaltung. Quelle ueber event_id|event_uuid|event_number. '
            . 'Optionale overrides: name, status, start_date, end_date, customer, event_type, responsible, responsible_onsite, '
            . 'CRM-FKs (crm_company_id, orderer_crm_*, invoice_crm_*), follow_up_date, follow_up_note. '
            . 'Optionale include-Flags: include_days (default true), include_bookings (default true), '
            . 'include_schedule_items (default true), include_notes (default false). '
            . 'Quotes und Orders werden NICHT mitkopiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                // Override-Felder (alle optional)
                'name'               => ['type' => 'string', 'description' => 'Neuer Event-Name. Default: "<alter Name> (Kopie)".'],
                'status'             => ['type' => 'string', 'description' => 'Status-Override. Default: "Option".'],
                'start_date'         => ['type' => 'string', 'description' => 'YYYY-MM-DD. Wenn weggelassen: Quelle uebernehmen.'],
                'end_date'           => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
                'customer'           => ['type' => 'string'],
                'event_type'         => ['type' => 'string'],
                'responsible'        => ['type' => 'string'],
                'responsible_onsite' => ['type' => 'string'],
                'follow_up_date'     => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'follow_up_note'     => ['type' => 'string'],
                'crm_company_id'                  => ['type' => 'integer'],
                'organizer_crm_contact_id'        => ['type' => 'integer'],
                'organizer_onsite_crm_contact_id' => ['type' => 'integer'],
                'orderer_crm_company_id'          => ['type' => 'integer'],
                'orderer_crm_contact_id'          => ['type' => 'integer'],
                'invoice_crm_company_id'          => ['type' => 'integer'],
                'invoice_crm_contact_id'          => ['type' => 'integer'],
                'delivery_address_crm_company_id' => ['type' => 'integer'],
                'delivery_location_id'            => ['type' => 'integer'],
                // Include-Flags
                'include_days'           => ['type' => 'boolean', 'description' => 'EventDays mitkopieren. Default: true.'],
                'include_bookings'       => ['type' => 'boolean', 'description' => 'Raumbuchungen mitkopieren. Default: true.'],
                'include_schedule_items' => ['type' => 'boolean', 'description' => 'Ablaufplan mitkopieren. Default: true.'],
                'include_notes'          => ['type' => 'boolean', 'description' => 'EventNotes mitkopieren. Default: false (eventspezifisch).'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $source = $this->resolveEvent($arguments, $context);
            if ($source instanceof ToolResult) {
                return $source;
            }

            // Override-Felder aus arguments extrahieren (nur die, die ueberhaupt drin sind).
            $overrideFields = [
                'name', 'status', 'start_date', 'end_date', 'customer', 'event_type',
                'responsible', 'responsible_onsite', 'follow_up_date', 'follow_up_note',
                'crm_company_id', 'organizer_crm_contact_id', 'organizer_onsite_crm_contact_id',
                'orderer_crm_company_id', 'orderer_crm_contact_id',
                'invoice_crm_company_id', 'invoice_crm_contact_id',
                'delivery_address_crm_company_id', 'delivery_location_id',
            ];
            $overrides = [];
            foreach ($overrideFields as $f) {
                if (array_key_exists($f, $arguments)) {
                    $overrides[$f] = $arguments[$f];
                }
            }

            $include = [
                'days'           => (bool) ($arguments['include_days']           ?? true),
                'bookings'       => (bool) ($arguments['include_bookings']       ?? true),
                'schedule_items' => (bool) ($arguments['include_schedule_items'] ?? true),
                'notes'          => (bool) ($arguments['include_notes']          ?? false),
            ];

            $copy = EventCloner::clone($source, $context->user, $overrides, $include);
            $copy->load(['days', 'bookings', 'scheduleItems']);

            return ToolResult::success([
                'id'           => $copy->id,
                'uuid'         => $copy->uuid,
                'slug'         => $copy->slug,
                'event_number' => $copy->event_number,
                'name'         => $copy->name,
                'status'       => $copy->status,
                'start_date'   => $copy->start_date?->toDateString(),
                'end_date'     => $copy->end_date?->toDateString(),
                'team_id'      => $copy->team_id,
                'days_copied'           => $include['days']           ? $copy->days->count()          : 0,
                'bookings_copied'       => $include['bookings']       ? $copy->bookings->count()      : 0,
                'schedule_items_copied' => $include['schedule_items'] ? $copy->scheduleItems->count() : 0,
                'message'      => "Event '{$source->event_number}' geklont als '{$copy->event_number}'.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Klonen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'event', 'clone', 'duplicate'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
