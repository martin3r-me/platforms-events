<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Event;
use Platform\Events\Tools\Concerns\HintsIgnoredFields;
use Platform\Events\Tools\Concerns\RecommendsMissingFields;
use Platform\Events\Tools\Concerns\ResolvesLocationRefInput;
use Platform\Events\Tools\Concerns\ValidatesMrData;

/**
 * Aktualisiert ein Event. Nur übergebene Felder werden geändert.
 */
class UpdateEventTool implements ToolContract, ToolMetadataContract
{
    use HintsIgnoredFields;
    use RecommendsMissingFields;
    use ResolvesLocationRefInput;
    use ValidatesMrData;

    protected const UPDATABLE_STRING_FIELDS = [
        'name', 'customer', 'group', 'location', 'status', 'event_type',
        'organizer_contact', 'organizer_contact_onsite', 'organizer_for_whom',
        'orderer_company', 'orderer_contact', 'orderer_via',
        'invoice_to', 'invoice_contact', 'invoice_date_type',
        'responsible', 'responsible_onsite', 'cost_center', 'cost_carrier', 'quote_price_mode',
        'sign_left', 'sign_right',
        'follow_up_note',
        'delivery_address', 'delivery_note',
        'inquiry_time', 'potential',
        'forwarding_time',
        // Schluss-Bewertung
        'internal_rating', 'customer_satisfaction', 'rebooking_recommendation',
    ];

    protected const UPDATABLE_DATE_FIELDS = [
        'start_date', 'end_date', 'follow_up_date', 'inquiry_date', 'forwarding_date',
    ];

    protected const UPDATABLE_FK_FIELDS = [
        'crm_company_id',
        'organizer_crm_contact_id', 'organizer_onsite_crm_contact_id',
        'orderer_crm_company_id', 'orderer_crm_contact_id',
        'invoice_crm_company_id', 'invoice_crm_contact_id',
        'delivery_address_crm_company_id', 'delivery_location_id',
    ];

    public function getName(): string
    {
        return 'events.events.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/{id} - Aktualisiert ein Event. Identifikation: event_id ODER uuid ODER event_number. '
            . 'Alle übrigen Felder aus dem Event-Model sind optional (siehe events.event.GET für verfügbare Felder). '
            . 'Nur übergebene Werte werden geändert. mr_data wird komplett ersetzt (merge bitte clientseitig). '
            . 'mr_data ist STRIKT validiert: Keys müssen dem Label aus Einstellungen → Management Report entsprechen '
            . '(z.B. "Speisenform") oder der kanonischen ID ("mrf_<id>"); Werte müssen aus den konfigurierten '
            . 'Optionen stammen. Unbekannte Keys oder Werte führen zu VALIDATION_ERROR mit Liste der erlaubten Werte.';
    }

    public function getSchema(): array
    {
        $stringFields = [];
        foreach (self::UPDATABLE_STRING_FIELDS as $f) {
            $stringFields[$f] = ['type' => 'string'];
        }
        $dateFields = [];
        foreach (self::UPDATABLE_DATE_FIELDS as $f) {
            $dateFields[$f] = ['type' => 'string', 'description' => 'YYYY-MM-DD'];
        }
        $fkFields = [];
        foreach (self::UPDATABLE_FK_FIELDS as $f) {
            $fkFields[$f] = ['type' => 'integer', 'description' => 'FK (null setzbar via leerem String)'];
        }

        return [
            'type' => 'object',
            'properties' => array_merge([
                'event_id'             => ['type' => 'integer'],
                'uuid'                 => ['type' => 'string'],
                'event_number'         => ['type' => 'string'],
                'mr_data'              => ['type' => 'object', 'description' => 'Management-Report als Key/Value-Map (ersetzt den gesamten Inhalt). STRIKT: Keys = Feld-Label aus Einstellungen → MR (z.B. "Speisenform") oder "mrf_<id>"; Werte nur aus konfigurierten Optionen.'],
                'forwarded'            => ['type' => 'boolean'],
                'is_highlight'         => ['type' => 'boolean', 'description' => '[Highlight] Veranstaltung als „besonders sehenswert" markieren (Foto-Termin, vor Ort sein).'],
                'delivery_location_uuid'    => ['type' => 'string',  'description' => '[Lieferung] Location-UUID (alternativ zu delivery_location_id).'],
                'delivery_location_kuerzel' => ['type' => 'string',  'description' => '[Lieferung] Location-Kuerzel (per Team eindeutig, TRIM+UPPER).'],
                'delivery_location_ref'     => ['description' => '[Lieferung] Generischer Resolver: numerisch->ID, UUID->uuid, sonst Kuerzel.'],
            ], $stringFields, $dateFields, $fkFields),
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

            // delivery_location_* (uuid/kuerzel/ref) -> delivery_location_id aufloesen.
            $deliveryLocationAliases = [];
            $deliveryLocResolve = $this->resolveLocationRefInput($arguments, (int) $event->team_id, 'delivery_');
            if ($deliveryLocResolve['error']) {
                return $deliveryLocResolve['error'];
            }
            if ($deliveryLocResolve['location']) {
                $arguments['delivery_location_id'] = $deliveryLocResolve['location']->id;
                $deliveryLocationAliases = $deliveryLocResolve['aliases_applied'];
            }

            // potential ist ein Enum – nur vordefinierte Werte zulassen.
            if (array_key_exists('potential', $arguments) && $arguments['potential'] !== null && $arguments['potential'] !== '') {
                $allowed = CreateEventTool::POTENTIAL_OPTIONS;
                if (!in_array($arguments['potential'], $allowed, true)) {
                    return ToolResult::error(
                        'VALIDATION_ERROR',
                        'potential: nur folgende Werte sind erlaubt: "' . implode('" | "', $allowed) . '". Erhalten: "' . $arguments['potential'] . '".'
                    );
                }
            }

            $update = [];
            foreach (array_merge(self::UPDATABLE_STRING_FIELDS, self::UPDATABLE_DATE_FIELDS) as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f];
                }
            }
            if (array_key_exists('forwarded', $arguments)) {
                $update['forwarded'] = (bool) $arguments['forwarded'];
            }
            if (array_key_exists('is_highlight', $arguments)) {
                $update['is_highlight'] = (bool) $arguments['is_highlight'];
            }
            foreach (self::UPDATABLE_FK_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f] !== null && $arguments[$f] !== ''
                        ? (int) $arguments[$f]
                        : null;
                }
            }
            if (array_key_exists('mr_data', $arguments)) {
                if (!is_array($arguments['mr_data'])) {
                    $update['mr_data'] = null;
                } else {
                    $validation = $this->normalizeAndValidateMrData($arguments['mr_data'], (int) $event->team_id);
                    if ($validation['ok'] === false) {
                        return ToolResult::error('VALIDATION_ERROR', $validation['message']);
                    }
                    $update['mr_data'] = $validation['normalized'];
                }
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $known = array_merge(
                ['event_id', 'uuid', 'event_number', 'mr_data', 'forwarded', 'is_highlight'],
                $this->locationRefInputFields('delivery_'),
                self::UPDATABLE_STRING_FIELDS,
                self::UPDATABLE_DATE_FIELDS,
                self::UPDATABLE_FK_FIELDS,
            );
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $event->update($update);

            $response = [
                'id'             => $event->id,
                'uuid'           => $event->uuid,
                'slug'           => $event->slug,
                'event_number'   => $event->event_number,
                'name'           => $event->name,
                'status'         => $event->status,
                'team_id'        => $event->team_id,
                'updated_at'     => $event->updated_at?->toIso8601String(),
                'updated_fields' => array_keys($update),
                'aliases_applied' => $deliveryLocationAliases,
                'ignored_fields' => $ignored,
                'empty_recommended_fields'        => $this->emptyRecommendedFields($event),
                'empty_recommended_field_options' => $this->recommendedFieldOptions($event->team_id),
                'message'        => "Event '{$event->name}' erfolgreich aktualisiert.",
            ];
            if (!empty($ignored)) {
                $response['allowed_top_level_fields'] = array_values(array_unique($known));
                $hints = $this->hintsForIgnored($ignored);
                if (!empty($hints)) {
                    $response['ignored_hints'] = $hints;
                }
            }

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Events: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'event', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
