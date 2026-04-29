<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Services\EventFactory;
use Platform\Events\Tools\Concerns\RecommendsMissingFields;

/**
 * Erstellt ein neues Event. Pflicht: name. Empfohlen: start_date + end_date.
 *
 * Generiert automatisch event_number (VA#YYYY-MMx) pro Team und – falls
 * start_date gesetzt ist – Event-Days für den Datumsbereich.
 */
class CreateEventTool implements ToolContract, ToolMetadataContract
{
    use RecommendsMissingFields;

    /** Strikt erlaubte Werte fuer event.potential (LLM-Schutz vor Freitext). */
    public const POTENTIAL_OPTIONS = [
        '10% (unwahrscheinlich)',
        '30% (unverbindliche Anfrage)',
        '50% (Tendenz offen)',
        '70% (deutliche Tendenz zur Buchung)',
        '90% (ziemlich definitiv)',
    ];

    public function getName(): string
    {
        return 'events.events.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events - Erstellt ein Event. Pflicht: name. '
            . 'Felder sind in fachliche GRUPPEN organisiert (UI-Bereiche): '
            . '[Stammdaten] name, event_type, status, start_date, end_date, group, location (Legacy-Freitext); '
            . '[Kunde] customer (Legacy), crm_company_id (FK CRM-Firma); '
            . '[Veranstalter] organizer_contact, organizer_crm_contact_id, organizer_contact_onsite, organizer_onsite_crm_contact_id, organizer_for_whom; '
            . '[Besteller] orderer_company, orderer_contact, orderer_via, orderer_crm_company_id, orderer_crm_contact_id; '
            . '[Rechnung] invoice_to, invoice_contact, invoice_date_type (Default: erster Tag), invoice_crm_company_id, invoice_crm_contact_id; '
            . '[Zustaendigkeit] responsible, responsible_onsite, cost_center, cost_carrier, quote_price_mode (netto|brutto), sign_left, sign_right; '
            . '[Follow-Up] follow_up_date, follow_up_note; '
            . '[Lieferung] delivery_address (Freitext), delivery_address_crm_company_id (CRM-Firma), delivery_location_id (eigene Location), delivery_note – '
            . 'genau eine der drei Quellen befuellen, abhaengig vom Lieferadress-Typ; '
            . '[Eingang] inquiry_date, inquiry_time, potential; '
            . '[Weiterleitung] forwarded, forwarding_date, forwarding_time; '
            . '[Management Report] mr_data (object); '
            . '[Auto-Days] auto_create_days (boolean, default true) erzeugt EventDays aus start_date..end_date; '
            . 'pax / default_pax (int|string) wird in pers_von/pers_bis aller Tage gesetzt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                // [Stammdaten]
                'name'       => ['type' => 'string',  'description' => '[Stammdaten] Name der Veranstaltung (ERFORDERLICH).'],
                'team_id'    => ['type' => 'integer', 'description' => '[System] Optional: Team-ID. Default: aktuelles Team.'],
                'group'      => ['type' => 'string',  'description' => '[Stammdaten] Gruppe/Bereich (freitext).'],
                'location'   => ['type' => 'string',  'description' => '[Stammdaten] Ort (freitext, Legacy – fuer Lieferadresse stattdessen delivery_*).'],
                'start_date' => ['type' => 'string',  'description' => '[Stammdaten] YYYY-MM-DD.'],
                'end_date'   => ['type' => 'string',  'description' => '[Stammdaten] YYYY-MM-DD.'],
                'status'     => ['type' => 'string',  'description' => '[Stammdaten] Option | Definitiv | Vertrag | Abgeschlossen | Storno | Warteliste | Tendenz.'],
                'event_type' => ['type' => 'string',  'description' => '[Stammdaten] Anlass-Typ (siehe Settings → Anlass-Typen).'],

                // [Kunde]
                'customer'       => ['type' => 'string',  'description' => '[Kunde] Kundenname (freitext, Legacy). Bevorzugt crm_company_id verwenden.'],
                'crm_company_id' => ['type' => 'integer', 'description' => '[Kunde] FK crm_companies.id.'],

                // [Veranstalter]
                'organizer_contact'              => ['type' => 'string',  'description' => '[Veranstalter] Ansprechpartner (freitext).'],
                'organizer_crm_contact_id'       => ['type' => 'integer', 'description' => '[Veranstalter] FK crm_contacts.id (Ansprechpartner).'],
                'organizer_contact_onsite'       => ['type' => 'string',  'description' => '[Veranstalter] Ansprechpartner vor Ort (freitext).'],
                'organizer_onsite_crm_contact_id'=> ['type' => 'integer', 'description' => '[Veranstalter] FK crm_contacts.id (vor Ort).'],
                'organizer_for_whom'             => ['type' => 'string',  'description' => '[Veranstalter] Veranstaltung fuer (Anzeige-Text).'],

                // [Besteller]
                'orderer_company'        => ['type' => 'string',  'description' => '[Besteller] Firma (freitext).'],
                'orderer_contact'        => ['type' => 'string',  'description' => '[Besteller] Ansprechpartner (freitext).'],
                'orderer_via'            => ['type' => 'string',  'description' => '[Besteller] Eingangskanal: mail|phone|meeting|referral|other.'],
                'orderer_crm_company_id' => ['type' => 'integer', 'description' => '[Besteller] FK crm_companies.id.'],
                'orderer_crm_contact_id' => ['type' => 'integer', 'description' => '[Besteller] FK crm_contacts.id.'],

                // [Rechnung]
                'invoice_to'             => ['type' => 'string',  'description' => '[Rechnung] Adressat-Name (freitext).'],
                'invoice_contact'        => ['type' => 'string',  'description' => '[Rechnung] Ansprechpartner (freitext).'],
                'invoice_date_type'      => ['type' => 'string',  'description' => '[Rechnung] Rechnungsdatum (YYYY-MM-DD oder Wert aus dem EventDay-Dropdown). Default: erster Tag (start_date / erstes EventDay-datum). Manuell ueberschreibbar.'],
                'invoice_crm_company_id' => ['type' => 'integer', 'description' => '[Rechnung] FK crm_companies.id.'],
                'invoice_crm_contact_id' => ['type' => 'integer', 'description' => '[Rechnung] FK crm_contacts.id.'],

                // [Zustaendigkeit]
                'responsible'        => ['type' => 'string', 'description' => '[Zustaendigkeit] Hauptverantwortliche/r.'],
                'responsible_onsite' => ['type' => 'string', 'description' => '[Zustaendigkeit] Verantwortliche/r vor Ort am Veranstaltungstag.'],
                'cost_center'        => ['type' => 'string', 'description' => '[Zustaendigkeit] Kostenstelle (siehe Settings).'],
                'cost_carrier'       => ['type' => 'string', 'description' => '[Zustaendigkeit] Kostentraeger.'],
                'quote_price_mode'   => ['type' => 'string', 'description' => '[Zustaendigkeit] Preis-Modus fuer das Angebot: "netto" (default) oder "brutto".'],
                'sign_left'          => ['type' => 'string', 'description' => '[Zustaendigkeit] Linke Unterschrift.'],
                'sign_right'         => ['type' => 'string', 'description' => '[Zustaendigkeit] Rechte Unterschrift.'],

                // [Management Report]
                'mr_data' => ['type' => 'object', 'description' => '[Management Report] Werte als Key/Value-Map.'],

                // [Follow-Up]
                'follow_up_date' => ['type' => 'string', 'description' => '[Follow-Up] YYYY-MM-DD.'],
                'follow_up_note' => ['type' => 'string', 'description' => '[Follow-Up] Bemerkung.'],

                // [Lieferung] – exakt EINE der drei Quellen befuellen
                'delivery_address'                => ['type' => 'string',  'description' => '[Lieferung] Freitext-Adresse, wenn weder CRM-Firma noch eigene Location passt.'],
                'delivery_address_crm_company_id' => ['type' => 'integer', 'description' => '[Lieferung] FK crm_companies.id (Lieferadresse aus CRM).'],
                'delivery_location_id'            => ['type' => 'integer', 'description' => '[Lieferung] FK locations_locations.id (eigene Location).'],
                'delivery_note'                   => ['type' => 'string',  'description' => '[Lieferung] Hinweis (z.B. "Haupteingang").'],

                // [Eingang]
                'inquiry_date' => ['type' => 'string', 'description' => '[Eingang] YYYY-MM-DD (Default: heute).'],
                'inquiry_time' => ['type' => 'string', 'description' => '[Eingang] HH:MM (Default: jetzt).'],
                'potential'    => ['type' => 'string', 'enum' => self::POTENTIAL_OPTIONS, 'description' => '[Eingang] Wahrscheinlichkeit. STRIKT: nur die 5 vordefinierten Werte sind erlaubt – "10% (unwahrscheinlich)" | "30% (unverbindliche Anfrage)" | "50% (Tendenz offen)" | "70% (deutliche Tendenz zur Buchung)" | "90% (ziemlich definitiv)". Andere Werte (z.B. "60%") werden mit VALIDATION_ERROR abgelehnt.'],

                // [Weiterleitung]
                'forwarded'       => ['type' => 'boolean', 'description' => '[Weiterleitung] Wurde die Anfrage weitergeleitet?'],
                'forwarding_date' => ['type' => 'string',  'description' => '[Weiterleitung] YYYY-MM-DD.'],
                'forwarding_time' => ['type' => 'string',  'description' => '[Weiterleitung] HH:MM.'],

                // [Auto-Days]
                'auto_create_days' => ['type' => 'boolean', 'description' => '[Auto-Days] Default true: EventDays aus start_date..end_date anlegen.'],
                'pax'              => ['type' => ['integer', 'string'], 'description' => '[Auto-Days] Default-Personenzahl. Setzt pers_von/pers_bis aller Tage. Alias: default_pax.'],
                'default_pax'      => ['type' => ['integer', 'string'], 'description' => '[Auto-Days] Alias fuer pax.'],
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

            // potential ist ein Enum – nur vordefinierte Werte zulassen.
            if (array_key_exists('potential', $arguments) && $arguments['potential'] !== null && $arguments['potential'] !== '') {
                if (!in_array($arguments['potential'], self::POTENTIAL_OPTIONS, true)) {
                    return ToolResult::error(
                        'VALIDATION_ERROR',
                        'potential: nur folgende Werte sind erlaubt: "' . implode('" | "', self::POTENTIAL_OPTIONS) . '". Erhalten: "' . $arguments['potential'] . '".'
                    );
                }
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
                'inquiry_date', 'inquiry_time', 'potential',
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

            // pax / default_pax: KEIN Event-Feld, wird in EventFactory extrahiert und
            // beim Anlegen der EventDays auf pers_von/pers_bis gemappt.
            foreach (['pax', 'default_pax'] as $f) {
                if (array_key_exists($f, $arguments) && $arguments[$f] !== null && $arguments[$f] !== '') {
                    $data[$f] = $arguments[$f];
                }
            }

            $event = EventFactory::create(
                $context->user,
                $teamId,
                $data,
                $arguments['auto_create_days'] ?? true,
            );

            $event->refresh()->load('days');

            return ToolResult::success([
                'id'             => $event->id,
                'uuid'           => $event->uuid,
                'slug'           => $event->slug,
                'event_number'   => $event->event_number,
                'name'           => $event->name,
                'status'         => $event->status,
                'start_date'     => $event->start_date?->toDateString(),
                'end_date'       => $event->end_date?->toDateString(),
                'team_id'        => $event->team_id,
                'days_created'   => $event->days->count(),
                'empty_recommended_fields'         => $this->emptyRecommendedFields($event),
                'empty_recommended_field_options'  => $this->recommendedFieldOptions($event->team_id),
                'message'        => "Event '{$event->name}' erfolgreich erstellt (#{$event->event_number}).",
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
