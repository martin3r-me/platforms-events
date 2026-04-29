<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Services\EventFactory;

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
        return 'POST /events - Erstellt ein Event. Pflichtfeld: name. Optional: customer, crm_company_id, group, location, start_date, end_date, status, '
            . 'organizer_contact, organizer_crm_contact_id, organizer_contact_onsite, organizer_onsite_crm_contact_id, organizer_for_whom, '
            . 'orderer_company, orderer_contact, orderer_via, orderer_crm_company_id, orderer_crm_contact_id, '
            . 'invoice_to, invoice_contact, invoice_date_type, invoice_crm_company_id, invoice_crm_contact_id, '
            . 'responsible, responsible_onsite, cost_center, cost_carrier, quote_price_mode (netto|brutto), event_type, '
            . 'sign_left, sign_right, mr_data (object), follow_up_date, follow_up_note, '
            . 'delivery_address, delivery_address_crm_company_id, delivery_location_id, delivery_note, '
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
                'customer'   => ['type' => 'string',  'description' => 'Kundenname (freitext, legacy). Bevorzugt crm_company_id verwenden.'],
                'crm_company_id' => ['type' => 'integer', 'description' => 'FK auf crm_companies.id (Kunde).'],
                'group'      => ['type' => 'string'],
                'location'   => ['type' => 'string',  'description' => 'Ort (freitext, legacy).'],
                'start_date' => ['type' => 'string',  'description' => 'YYYY-MM-DD.'],
                'end_date'   => ['type' => 'string',  'description' => 'YYYY-MM-DD.'],
                'status'     => ['type' => 'string',  'description' => 'Option | Definitiv | Vertrag | Abgeschlossen | Storno | Warteliste | Tendenz'],
                'event_type' => ['type' => 'string'],

                'organizer_contact'              => ['type' => 'string'],
                'organizer_crm_contact_id'       => ['type' => 'integer', 'description' => 'FK crm_contacts.id (Veranstalter-Ansprechpartner).'],
                'organizer_contact_onsite'       => ['type' => 'string'],
                'organizer_onsite_crm_contact_id'=> ['type' => 'integer', 'description' => 'FK crm_contacts.id (Veranstalter vor Ort).'],
                'organizer_for_whom'             => ['type' => 'string'],

                'orderer_company'        => ['type' => 'string'],
                'orderer_contact'        => ['type' => 'string'],
                'orderer_via'            => ['type' => 'string', 'description' => 'mail|phone|meeting|referral|other'],
                'orderer_crm_company_id' => ['type' => 'integer', 'description' => 'FK crm_companies.id (Besteller-Firma).'],
                'orderer_crm_contact_id' => ['type' => 'integer', 'description' => 'FK crm_contacts.id (Besteller-Ansprechpartner).'],

                'invoice_to'             => ['type' => 'string'],
                'invoice_contact'        => ['type' => 'string'],
                'invoice_date_type'      => ['type' => 'string'],
                'invoice_crm_company_id' => ['type' => 'integer', 'description' => 'FK crm_companies.id (Rechnungs-Empfaenger).'],
                'invoice_crm_contact_id' => ['type' => 'integer', 'description' => 'FK crm_contacts.id (Rechnungs-Ansprechpartner).'],

                'responsible'        => ['type' => 'string', 'description' => 'Hauptverantwortliche/r (Name oder User-Identifier).'],
                'responsible_onsite' => ['type' => 'string', 'description' => 'Verantwortliche/r vor Ort am Veranstaltungstag.'],
                'cost_center'        => ['type' => 'string'],
                'cost_carrier'       => ['type' => 'string'],
                'quote_price_mode'   => ['type' => 'string', 'description' => 'Preis-Modus fuer das gesamte Angebot: "netto" (default) oder "brutto".'],

                'sign_left'  => ['type' => 'string'],
                'sign_right' => ['type' => 'string'],

                'mr_data' => ['type' => 'object', 'description' => 'Management-Report Werte als Key/Value-Map.'],

                'follow_up_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'follow_up_note' => ['type' => 'string'],

                'delivery_address'                => ['type' => 'string'],
                'delivery_address_crm_company_id' => ['type' => 'integer', 'description' => 'FK crm_companies.id (Lieferadresse aus CRM).'],
                'delivery_location_id'            => ['type' => 'integer', 'description' => 'FK auf locations_locations.id (eigene Location).'],
                'delivery_note'                   => ['type' => 'string',  'description' => 'Freitext, z.B. "Haupteingang".'],

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

            $data = [
                'name' => $arguments['name'],
            ];

            foreach ([
                'customer', 'group', 'location', 'start_date', 'end_date', 'event_type', 'status',
                'organizer_contact', 'organizer_contact_onsite', 'organizer_for_whom',
                'orderer_company', 'orderer_contact', 'orderer_via',
                'invoice_to', 'invoice_contact', 'invoice_date_type',
                'responsible', 'responsible_onsite', 'cost_center', 'cost_carrier', 'quote_price_mode',
                'sign_left', 'sign_right',
                'follow_up_date', 'follow_up_note',
                'delivery_address', 'delivery_note',
                'inquiry_date', 'inquiry_time', 'inquiry_note', 'potential',
                'forwarding_date', 'forwarding_time',
            ] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $data[$f] = $arguments[$f];
                }
            }
            // Integer-FKs (CRM/Locations) – nullable + Cast
            foreach ([
                'crm_company_id',
                'organizer_crm_contact_id', 'organizer_onsite_crm_contact_id',
                'orderer_crm_company_id', 'orderer_crm_contact_id',
                'invoice_crm_company_id', 'invoice_crm_contact_id',
                'delivery_address_crm_company_id', 'delivery_location_id',
            ] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $data[$f] = $arguments[$f] !== null && $arguments[$f] !== ''
                        ? (int) $arguments[$f]
                        : null;
                }
            }
            if (array_key_exists('forwarded', $arguments)) {
                $data['forwarded'] = (bool) $arguments['forwarded'];
            }
            if (array_key_exists('mr_data', $arguments) && is_array($arguments['mr_data'])) {
                $data['mr_data'] = $arguments['mr_data'];
            }

            $event = EventFactory::create(
                $context->user,
                $teamId,
                $data,
                $arguments['auto_create_days'] ?? true,
            );

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
