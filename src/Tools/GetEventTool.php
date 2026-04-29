<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Event;
use Platform\Events\Tools\Concerns\HydratesEventReferences;
use Platform\Events\Tools\Concerns\RecommendsMissingFields;

/**
 * Liefert Details zu einem Event, optional inkl. Tage/Buchungen/Ablauf/Notizen.
 * Foreign-Keys (CRM-Firmen, Lieferadresse, Locations) werden automatisch
 * hydratisiert – z. B. customer_company: {id, name} statt nackter ID.
 */
class GetEventTool implements ToolContract, ToolMetadataContract
{
    use HydratesEventReferences;
    use RecommendsMissingFields;

    public function getName(): string
    {
        return 'events.event.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{id} - Liefert Details zu einem Event. Identifikation: event_id ODER uuid ODER event_number (auch ohne "#"). '
            . 'Optional include: days, bookings, schedule, notes (alle true/false). '
            . 'Hydratisierungs-Pattern: Foreign-Keys liegen doppelt im Result – '
            . 'als Roh-FK (z.B. crm_company_id) UND als hydratisiertes Objekt (z.B. customer_company={id,name}). '
            . 'Hydratisierte Refs: customer_company, organizer_contact_ref, organizer_onsite_contact, '
            . 'orderer_company_ref, orderer_contact_ref, invoice_company, invoice_contact_ref, '
            . 'delivery_company (CRM-Firma als Lieferadresse) und delivery_location (eigener Raum). '
            . 'Abgeleitete Felder: primary_location (erste Buchung mit location_id) sowie '
            . 'delivery = {source, label, location?|company?|address?, note} – Aggregat ueber alle drei Lieferadress-Quellen. '
            . 'Legacy-Felder customer/location bleiben parallel (deprecated).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'event_id'         => ['type' => 'integer', 'description' => 'ID des Events. Alternative zu uuid/event_number.'],
                'uuid'             => ['type' => 'string',  'description' => 'UUID des Events.'],
                'event_number'     => ['type' => 'string',  'description' => 'VA-Nummer (z.B. "VA#2026-031" oder "VA2026-031").'],
                'include_days'     => ['type' => 'boolean', 'description' => 'Optional: Event-Tage mitliefern.'],
                'include_bookings' => ['type' => 'boolean', 'description' => 'Optional: Raum-Buchungen mitliefern.'],
                'include_schedule' => ['type' => 'boolean', 'description' => 'Optional: Ablaufplan mitliefern.'],
                'include_notes'    => ['type' => 'boolean', 'description' => 'Optional: Notizen mitliefern.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = Event::query();
            if (!empty($arguments['event_id'])) {
                $query->where('id', (int) $arguments['event_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } elseif (!empty($arguments['event_number'])) {
                $raw = (string) $arguments['event_number'];
                $query->where(function ($q) use ($raw) {
                    $q->where('event_number', $raw)
                      ->orWhere('event_number', preg_replace('/^(VA)(\d)/', '$1#$2', $raw));
                });
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'event_id, uuid oder event_number ist erforderlich.');
            }

            $event = $query->first();
            if (!$event) {
                return ToolResult::error('EVENT_NOT_FOUND', 'Das angegebene Event wurde nicht gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', $event->team_id)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Event.');
            }

            $payload = [
                'id'                => $event->id,
                'uuid'              => $event->uuid,
                'slug'              => $event->slug,
                'event_number'      => $event->event_number,
                'name'              => $event->name,
                'customer'          => $event->customer,
                'group'             => $event->group,
                'location'          => $event->location,
                'start_date'        => $event->start_date?->toDateString(),
                'end_date'          => $event->end_date?->toDateString(),
                'status'            => $event->status,
                'status_changed_at' => $event->status_changed_at?->toIso8601String(),
                'event_type'        => $event->event_type,

                'organizer_contact'        => $event->organizer_contact,
                'organizer_contact_onsite' => $event->organizer_contact_onsite,
                'organizer_for_whom'       => $event->organizer_for_whom,

                'orderer_company' => $event->orderer_company,
                'orderer_contact' => $event->orderer_contact,
                'orderer_via'     => $event->orderer_via,

                'invoice_to'        => $event->invoice_to,
                'invoice_contact'   => $event->invoice_contact,
                'invoice_date_type' => $event->invoice_date_type,

                'responsible'  => $event->responsible,
                'cost_center'  => $event->cost_center,
                'cost_carrier' => $event->cost_carrier,

                'sign_left'  => $event->sign_left,
                'sign_right' => $event->sign_right,

                'mr_data' => $event->mr_data,

                'follow_up_date'  => $event->follow_up_date?->toDateString(),
                'follow_up_note'  => $event->follow_up_note,
                'delivery_address'     => $event->delivery_address,
                'delivery_location_id' => $event->delivery_location_id,
                'delivery_note'        => $event->delivery_note,

                'inquiry_date' => $event->inquiry_date?->toDateString(),
                'inquiry_time' => $event->inquiry_time,
                'potential'    => $event->potential,

                'forwarded'        => (bool) $event->forwarded,
                'forwarding_date'  => $event->forwarding_date?->toDateString(),
                'forwarding_time'  => $event->forwarding_time,

                'team_id'    => $event->team_id,
                'user_id'    => $event->user_id,
                'created_at' => $event->created_at?->toIso8601String(),
                'updated_at' => $event->updated_at?->toIso8601String(),

                // Roh-FKs (fuer Updates / Tool-Calls)
                'crm_company_id'                  => $event->crm_company_id,
                'orderer_crm_company_id'          => $event->orderer_crm_company_id,
                'orderer_crm_contact_id'          => $event->orderer_crm_contact_id,
                'invoice_crm_company_id'          => $event->invoice_crm_company_id,
                'invoice_crm_contact_id'          => $event->invoice_crm_contact_id,
                'organizer_crm_contact_id'        => $event->organizer_crm_contact_id,
                'organizer_onsite_crm_contact_id' => $event->organizer_onsite_crm_contact_id,
                'delivery_address_crm_company_id' => $event->delivery_address_crm_company_id,
                'delivery_location_id'            => $event->delivery_location_id,
            ];

            // Hydratisierte Refs ({id, name, ...} statt nur ID).
            $payload = array_merge($payload, $this->hydrateEventReferences($event));

            // Abgeleitete primary_location: erste Buchung mit location_id (sortiert).
            $primaryBooking = $event->bookings()->whereNotNull('location_id')
                ->with('location')
                ->orderBy('sort_order')->orderBy('datum')
                ->first();
            $payload['primary_location'] = $primaryBooking?->location ? [
                'id'      => $primaryBooking->location->id,
                'name'    => $primaryBooking->location->name,
                'kuerzel' => $primaryBooking->location->kuerzel,
                'gruppe'  => $primaryBooking->location->gruppe,
            ] : null;

            // Abgeleitetes delivery-Aggregat: liefert genau eine sichtbare Quelle
            // (own_location | crm_company | freetext | none). Source-Priorisierung
            // = location > crm_company > freetext (entspricht UI-Pattern).
            $deliveryAggregate = ['source' => 'none', 'label' => null, 'note' => $event->delivery_note ?: null];
            if (!empty($payload['delivery_location'])) {
                $deliveryAggregate['source']   = 'own_location';
                $deliveryAggregate['label']    = $payload['delivery_location']['name'] ?? null;
                $deliveryAggregate['location'] = $payload['delivery_location'];
            } elseif (!empty($payload['delivery_company'])) {
                $deliveryAggregate['source']  = 'crm_company';
                $deliveryAggregate['label']   = $payload['delivery_company']['name'] ?? null;
                $deliveryAggregate['company'] = $payload['delivery_company'];
            } elseif (!empty($event->delivery_address)) {
                $deliveryAggregate['source']  = 'freetext';
                $deliveryAggregate['label']   = $event->delivery_address;
                $deliveryAggregate['address'] = $event->delivery_address;
            }
            $payload['delivery'] = $deliveryAggregate;

            // Hinweis-Block: Legacy-Felder, die parallel weiterhin im Result stehen.
            // Aufrufer sollten die genannten Ersatz-Felder bevorzugen.
            // Empfohlene, aber leere Felder (Self-Documentation fuer den Aufrufer).
            $payload['empty_recommended_fields'] = $this->emptyRecommendedFields($event);
            $payload['empty_recommended_field_options'] = $this->recommendedFieldOptions($event->team_id);

            $payload['_deprecated'] = [
                'customer'        => 'Legacy-Freitext. Bevorzugt: crm_company_id + customer_company.',
                'location'        => 'Legacy-Freitext (Veranstaltungsort). Lieferadresse via delivery_*; Buchungs-Raeume ueber bookings/primary_location.',
                'orderer_company' => 'Legacy-Freitext. Bevorzugt: orderer_crm_company_id + orderer_company_ref.',
                'orderer_contact' => 'Legacy-Freitext. Bevorzugt: orderer_crm_contact_id + orderer_contact_ref.',
                'invoice_to'      => 'Legacy-Freitext. Bevorzugt: invoice_crm_company_id + invoice_company.',
                'invoice_contact' => 'Legacy-Freitext. Bevorzugt: invoice_crm_contact_id + invoice_contact_ref.',
                'organizer_contact'        => 'Legacy-Freitext. Bevorzugt: organizer_crm_contact_id + organizer_contact_ref.',
                'organizer_contact_onsite' => 'Legacy-Freitext. Bevorzugt: organizer_onsite_crm_contact_id + organizer_onsite_contact.',
            ];

            if (!empty($arguments['include_days'])) {
                $payload['days'] = $event->days()->orderBy('sort_order')->get()
                    ->map(fn($d) => [
                        'id' => $d->id, 'uuid' => $d->uuid,
                        'label' => $d->label, 'datum' => $d->datum?->toDateString(),
                        'day_of_week' => $d->day_of_week, 'von' => $d->von, 'bis' => $d->bis,
                        'pers_von' => $d->pers_von, 'pers_bis' => $d->pers_bis,
                        'day_status' => $d->day_status, 'color' => $d->color,
                        'sort_order' => $d->sort_order,
                    ])->all();
            }
            if (!empty($arguments['include_bookings'])) {
                $payload['bookings'] = $event->bookings()->with('location')->orderBy('sort_order')->get()
                    ->map(fn($b) => [
                        'id' => $b->id, 'uuid' => $b->uuid,
                        'location_id'      => $b->location_id,
                        'location'         => $b->location ? [
                            'id'      => $b->location->id,
                            'name'    => $b->location->name,
                            'kuerzel' => $b->location->kuerzel,
                            'gruppe'  => $b->location->gruppe,
                        ] : null,
                        'raum' => $b->raum,
                        'datum' => $b->datum, 'beginn' => $b->beginn, 'ende' => $b->ende,
                        'pers' => $b->pers,
                        'pers_numeric' => is_numeric($b->pers) ? (int) $b->pers : null,
                        'bestuhlung' => $b->bestuhlung,
                        'optionsrang' => $b->optionsrang, 'absprache' => $b->absprache,
                        'sort_order' => $b->sort_order,
                    ])->all();
            }
            if (!empty($arguments['include_schedule'])) {
                $payload['schedule'] = $event->scheduleItems()->orderBy('sort_order')->get()
                    ->map(fn($s) => [
                        'id' => $s->id, 'uuid' => $s->uuid,
                        'datum' => $s->datum, 'von' => $s->von, 'bis' => $s->bis,
                        'beschreibung' => $s->beschreibung, 'raum' => $s->raum,
                        'bemerkung' => $s->bemerkung, 'linked' => (bool) $s->linked,
                        'sort_order' => $s->sort_order,
                    ])->all();
            }
            if (!empty($arguments['include_notes'])) {
                $payload['notes'] = $event->notes()->orderByDesc('created_at')->get()
                    ->map(fn($n) => [
                        'id' => $n->id, 'uuid' => $n->uuid,
                        'type' => $n->type, 'text' => $n->text, 'user_name' => $n->user_name,
                        'created_at' => $n->created_at?->toIso8601String(),
                    ])->all();
            }

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Events: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'event', 'get'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
