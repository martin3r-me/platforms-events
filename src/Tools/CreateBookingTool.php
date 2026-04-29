<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Booking;
use Platform\Events\Models\EventDay;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
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

    public function getName(): string
    {
        return 'events.bookings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/bookings - Legt eine oder mehrere Raum-Buchungen an. '
            . 'Pflicht: event-Selector + (location_id ODER raum). '
            . 'Bulk: apply_to_all_days=true (pro EventDay eine Buchung mit datum=Tag) ODER day_ids=[id,...] '
            . '(nur an diese Tage). Sonst: Single-Booking mit optionalem datum (YYYY-MM-DD oder Freitext). '
            . 'Defaults: optionsrang="1. Option" wenn nicht gesetzt. '
            . 'Felder: location_id (FK locations_locations.id, bevorzugt), raum (Legacy-Fallback), '
            . 'datum, beginn (HH:MM), ende, pers, bestuhlung, optionsrang ("1. Option"|"2. Option"|"Definitiv"|"Vertrag"), absprache.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'location_id'        => ['type' => 'integer', 'description' => 'FK auf locations_locations.id – bevorzugt gegenueber raum.'],
                'raum'               => ['type' => 'string',  'description' => 'Legacy-Fallback-Kürzel.'],
                'datum'              => ['type' => 'string',  'description' => 'YYYY-MM-DD oder freitext (nur Single-Modus; bei Bulk wird datum aus EventDay übernommen).'],
                'beginn'             => ['type' => 'string',  'description' => 'HH:MM.'],
                'ende'               => ['type' => 'string',  'description' => 'HH:MM.'],
                'pers'               => ['type' => 'string',  'description' => 'Personenzahl als String (Freitext erlaubt).'],
                'bestuhlung'         => ['type' => 'string',  'description' => 'z.B. "Bankett", "U-Form" – siehe Settings → Bestuhlungs-Arten.'],
                'optionsrang'        => ['type' => 'string',  'description' => 'Default "1. Option". Werte: "1. Option" | "2. Option" | "Definitiv" | "Vertrag".'],
                'absprache'          => ['type' => 'string',  'description' => 'Freitext-Kommentar.'],
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
                // Bulk-Modus: pro Tag eine Buchung mit datum=Tag
                foreach ($targetDays as $day) {
                    $maxSort++;
                    $booking = Booking::create(array_merge($base, [
                        'datum'      => $day->datum?->format('Y-m-d') ?? $day->datum,
                        'sort_order' => $maxSort,
                    ]));
                    $created[] = [
                        'id'          => $booking->id,
                        'uuid'        => $booking->uuid,
                        'event_day_id'=> $day->id,
                        'datum'       => $booking->datum,
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
                    'id'    => $booking->id,
                    'uuid'  => $booking->uuid,
                    'datum' => $booking->datum,
                ];
            }

            return ToolResult::success([
                'event_id'      => $event->id,
                'event_number'  => $event->event_number,
                'location_id'   => $locationId,
                'location_name' => $loc?->name,
                'optionsrang'   => $base['optionsrang'],
                'count'         => count($created),
                'bookings'      => $created,
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
