<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Booking;
use Platform\Events\Models\EventDay;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\NormalizesTimeFields;
use Platform\Events\Tools\Concerns\ResolvesEvent;
use Platform\Locations\Models\Location;

/**
 * Legt eine oder mehrere Raum-Buchungen an. Single-Booking ist Default
 * (ein Tag oder freitext-datum). Bulk-Modi:
 *   - apply_to_all_days = true  → pro EventDay eine Buchung mit datum=Tag.
 *   - day_ids = [int,...]       → nur an diese Tage je eine Buchung.
 * Recalc/Idempotenz: Bookings werden additiv angelegt (kein Replace).
 */
class CreateBookingTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;
    use CollectsValidationErrors;
    use NormalizesTimeFields;

    public function getName(): string
    {
        return 'events.bookings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/bookings - Legt eine oder mehrere Raum-Buchungen an. '
            . 'Pflicht: event-Selector + (location_id ODER raum). '
            . 'Bulk-Modi: apply_to_all_days=true (pro EventDay eine Buchung mit datum=Tag) ODER day_ids=[id,...] '
            . '(nur an diese Tage). Im Bulk werden beginn/ende/pers/bestuhlung/optionsrang/absprache identisch in alle '
            . 'erzeugten Buchungen geschrieben – nur datum kommt vom Tag. Sonst Single-Booking. '
            . 'Defaults: optionsrang="1. Option". '
            . 'Buchungs-Felder (vollstaendig): '
            . 'location_id (int, FK locations_locations.id, bevorzugt), '
            . 'raum (string, Legacy-Fallback-Kuerzel), '
            . 'datum (YYYY-MM-DD oder Freitext; bei Bulk ignoriert), '
            . 'beginn (HH:MM), ende (HH:MM), pers (string – Personenzahl), '
            . 'bestuhlung (string, siehe Settings → Bestuhlungs-Arten), '
            . 'optionsrang ("1. Option" | "2. Option" | "Definitiv" | "Vertrag"; default "1. Option"), '
            . 'absprache (Freitext). '
            . 'WICHTIG: Tag-Feldnamen werden ebenfalls akzeptiert und automatisch gemappt: '
            . 'von→beginn, bis→ende, pers_von/pers_bis→pers (pers hat Vorrang, sonst pers_von, sonst pers_bis). '
            . 'Unbekannte Felder werden im Result als ignored_fields[] zurueckgegeben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'location_id'        => ['type' => 'integer', 'description' => 'FK auf locations_locations.id – bevorzugt gegenueber raum.'],
                'raum'               => ['type' => 'string',  'description' => 'Legacy-Fallback-Kürzel.'],
                'datum'              => ['type' => 'string',  'description' => 'YYYY-MM-DD oder freitext (nur Single-Modus; bei Bulk wird datum aus EventDay übernommen).'],
                'beginn'             => ['type' => 'string',  'description' => 'HH:MM. Alias: "von" (Tag-Feldname).'],
                'ende'               => ['type' => 'string',  'description' => 'HH:MM. Alias: "bis" (Tag-Feldname).'],
                'pers'               => ['type' => 'string',  'description' => 'Personenzahl als String. Aliases: "pers_von"/"pers_bis" (Tag-Feldnamen).'],
                'bestuhlung'         => ['type' => 'string',  'description' => 'z.B. "Bankett", "U-Form" – siehe Settings → Bestuhlungs-Arten.'],
                'optionsrang'        => ['type' => 'string',  'description' => 'Default "1. Option". Werte: "1. Option" | "2. Option" | "Definitiv" | "Vertrag".'],
                'absprache'          => ['type' => 'string',  'description' => 'Freitext-Kommentar.'],
                // Tag-Feld-Aliase (werden auf beginn/ende/pers gemappt)
                'von'                => ['type' => 'string',  'description' => 'Alias fuer beginn (Tag-Feldname). Wird gemappt.'],
                'bis'                => ['type' => 'string',  'description' => 'Alias fuer ende (Tag-Feldname). Wird gemappt.'],
                'pers_von'           => ['type' => 'string',  'description' => 'Alias fuer pers (Tag-Feldname). Wird gemappt.'],
                'pers_bis'           => ['type' => 'string',  'description' => 'Alias fuer pers (Tag-Feldname). Wird gemappt, falls pers/pers_von nicht gesetzt.'],
                // Bulk-Modi
                'apply_to_all_days'  => ['type' => 'boolean', 'description' => 'Default false. Wenn true: pro EventDay eine Buchung mit datum=Tag.'],
                'day_ids'            => ['type' => 'array',   'items' => ['type' => 'integer'], 'description' => 'Nur an diese EventDay-IDs Buchungen anlegen (Bulk-Modus).'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) {
                return $event;
            }

            // Aliases (von/bis/start_time/end_time → beginn/ende; pers_von/pers_bis/pax → pers).
            $aliases = $this->normalizeTimeFields($arguments, ['start' => 'beginn', 'end' => 'ende', 'pers' => 'pers']);

            // Bekannte Felder zur Erkennung von ignored_fields
            $known = array_merge([
                'event_id', 'event_uuid', 'event_number',
                'location_id', 'raum', 'datum', 'beginn', 'ende', 'pers', 'bestuhlung', 'optionsrang', 'absprache',
                'apply_to_all_days', 'day_ids',
            ], $this->timeFieldAliases());
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $errors = [];

            $locationId = !empty($arguments['location_id']) ? (int) $arguments['location_id'] : null;
            $raum = $arguments['raum'] ?? null;
            if (!$locationId && empty($raum)) {
                $errors[] = $this->validationError('location_id|raum', 'location_id oder raum ist erforderlich.');
            }

            $loc = null;
            if ($locationId) {
                $loc = Location::find($locationId);
                if (!$loc) {
                    $errors[] = $this->validationError('location_id', "Location-ID {$locationId} existiert nicht.");
                } elseif ($loc->team_id !== $event->team_id) {
                    $errors[] = $this->validationError('location_id', 'Location gehoert einem anderen Team als das Event.');
                }
            }

            // Bulk-Modus auswerten
            $applyAllDays = (bool) ($arguments['apply_to_all_days'] ?? false);
            $dayIds = isset($arguments['day_ids']) && is_array($arguments['day_ids'])
                ? array_values(array_unique(array_filter(array_map('intval', $arguments['day_ids']))))
                : [];

            $targetDays = collect();
            if ($applyAllDays || !empty($dayIds)) {
                $q = EventDay::where('event_id', $event->id);
                if (!empty($dayIds)) {
                    $q->whereIn('id', $dayIds);
                }
                $targetDays = $q->orderBy('sort_order')->get();
                if ($targetDays->isEmpty()) {
                    $errors[] = $this->validationError('day_ids', 'Keine passenden EventDays gefunden.');
                } elseif (!empty($dayIds) && $targetDays->count() !== count($dayIds)) {
                    $found = $targetDays->pluck('id')->all();
                    $missing = array_values(array_diff($dayIds, $found));
                    $errors[] = $this->validationError('day_ids', 'Nicht alle day_ids gehoeren zum Event. Fehlend: ' . implode(', ', $missing));
                }
            }

            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }

            $base = [
                'event_id'    => $event->id,
                'team_id'     => $event->team_id,
                'user_id'     => $context->user->id,
                'location_id' => $locationId,
                'raum'        => $raum,
                'beginn'      => $arguments['beginn']      ?? null,
                'ende'        => $arguments['ende']        ?? null,
                'pers'        => $arguments['pers']        ?? null,
                'bestuhlung'  => $arguments['bestuhlung']  ?? null,
                'optionsrang' => $arguments['optionsrang'] ?? '1. Option',
                'absprache'   => $arguments['absprache']   ?? null,
            ];

            $maxSort = (int) Booking::where('event_id', $event->id)->max('sort_order');
            $created = [];

            if ($targetDays->isNotEmpty()) {
                // Bulk-Modus: pro Tag eine Buchung mit datum=Tag.
                // Tag-Defaults greifen, wenn beginn/ende/pers im POST nicht gesetzt waren –
                // dann wird pro Tag aus von/bis/pers_von das Tag-spezifische Default uebernommen.
                $bulkDefaultFromDay = empty($base['beginn']) && empty($base['ende']) && empty($base['pers']);
                foreach ($targetDays as $day) {
                    $maxSort++;
                    $row = array_merge($base, [
                        'datum'      => $day->datum?->format('Y-m-d') ?? $day->datum,
                        'sort_order' => $maxSort,
                    ]);
                    if ($bulkDefaultFromDay) {
                        $row['beginn'] = $day->von ?: $row['beginn'];
                        $row['ende']   = $day->bis ?: $row['ende'];
                        $row['pers']   = $day->pers_von ?: ($day->pers_bis ?: $row['pers']);
                    }
                    $booking = Booking::create($row);
                    $created[] = [
                        'id'           => $booking->id,
                        'uuid'         => $booking->uuid,
                        'event_day_id' => $day->id,
                        'datum'        => $booking->datum,
                        'beginn'       => $booking->beginn,
                        'ende'         => $booking->ende,
                        'pers'         => $booking->pers,
                        'pers_numeric' => is_numeric($booking->pers) ? (int) $booking->pers : null,
                        'source'       => $bulkDefaultFromDay ? 'day_defaults' : 'request',
                    ];
                }
            } else {
                // Single-Modus
                $maxSort++;
                $booking = Booking::create(array_merge($base, [
                    'datum'      => $arguments['datum'] ?? null,
                    'sort_order' => $maxSort,
                ]));
                $created[] = [
                    'id'           => $booking->id,
                    'uuid'         => $booking->uuid,
                    'datum'        => $booking->datum,
                    'beginn'       => $booking->beginn,
                    'ende'         => $booking->ende,
                    'pers'         => $booking->pers,
                    'pers_numeric' => is_numeric($booking->pers) ? (int) $booking->pers : null,
                ];
            }

            // primary_location nach Anlegen berechnen (erste Buchung mit location_id).
            // Convenience: spart einen separaten events.event.GET nach dem Bulk-POST.
            $primaryBooking = $event->bookings()->whereNotNull('location_id')
                ->with('location')
                ->orderBy('sort_order')->orderBy('datum')
                ->first();
            $primaryLocation = $primaryBooking?->location ? [
                'id'      => $primaryBooking->location->id,
                'name'    => $primaryBooking->location->name,
                'kuerzel' => $primaryBooking->location->kuerzel,
                'gruppe'  => $primaryBooking->location->gruppe,
            ] : null;

            return ToolResult::success([
                'event_id'      => $event->id,
                'event_number'  => $event->event_number,
                'location_id'   => $locationId,
                'location_name' => $loc?->name,
                'optionsrang'   => $base['optionsrang'],
                'count'         => count($created),
                'bookings'      => $created,
                'primary_location' => $primaryLocation,
                'aliases_applied'  => $aliases,
                'ignored_fields'   => $ignored,
                'message'       => count($created) === 1
                    ? "Buchung fuer Event #{$event->event_number} angelegt."
                    : count($created) . " Buchungen fuer Event #{$event->event_number} angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der Buchung: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'booking', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
