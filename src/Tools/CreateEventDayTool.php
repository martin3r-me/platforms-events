<?php

namespace Platform\Events\Tools;

use Carbon\Carbon;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;
use Platform\Events\Services\SettingsService;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\NormalizesTimeFields;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class CreateEventDayTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;
    use CollectsValidationErrors;
    use NormalizesTimeFields;

    /** Erlaubte Formate: #RGB | #RRGGBB | #RRGGBBAA (case-insensitive). */
    protected const COLOR_REGEX = '/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/';

    public function getName(): string
    {
        return 'events.days.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/days - Legt einen Event-Tag an. '
            . 'Pflicht: event-Selector + label + datum (YYYY-MM-DD). '
            . 'Optional: day_type (STRICT gegen Settings → Tages-Typen; Default "Veranstaltungstag" wenn nicht gesetzt – '
            . 'erlaubte Werte werden im Response unter empty_recommended_field_options.day_type.values gespiegelt; '
            . 'Tippfehler werden mit VALIDATION_ERROR abgelehnt; Liste in Einstellungen → Tages-Typen erweiterbar), '
            . 'von/bis (HH:MM), pers_von/pers_bis (Personenzahlen), '
            . 'day_status ("Option" [Default] | "Definitiv" | "Vertrag" ...), '
            . 'color (#RGB | #RRGGBB | #RRGGBBAA; wenn nicht gesetzt: DB-Default). '
            . 'day_of_week wird aus datum abgeleitet, wenn nicht uebergeben (So..Sa). '
            . 'sort_order wird automatisch (max+1) gesetzt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'label'       => ['type' => 'string',  'description' => 'Anzeigename des Tages (z.B. "Tag 1" oder "20.03.2026").'],
                'day_type'    => ['type' => 'string',  'description' => 'Veranstaltungstag|Aufbautag|Abbautag|Rüsttag. Default Veranstaltungstag.'],
                'datum'       => ['type' => 'string',  'description' => 'YYYY-MM-DD.'],
                'day_of_week' => ['type' => 'string'],
                'von'         => ['type' => 'string'],
                'bis'         => ['type' => 'string'],
                'pers_von'    => ['type' => 'string'],
                'pers_bis'    => ['type' => 'string'],
                'day_status'  => ['type' => 'string', 'description' => 'Option|Definitiv|Vertrag|...'],
                'color'       => ['type' => 'string', 'description' => 'Hex-Farbe, z.B. #6366f1. Optional (DB-Default greift sonst).'],
            ]),
            'required' => ['label', 'datum'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) {
                return $event;
            }

            // Aliases zwischen Tag/Buchung/Englisch normalisieren.
            $aliasesApplied = $this->normalizeTimeFields($arguments, ['start' => 'von', 'end' => 'bis']);
            // Pax-Single-Wert auf pers_von/pers_bis spiegeln.
            foreach (['pers', 'pax', 'persons'] as $alias) {
                if (!empty($arguments[$alias])) {
                    if (empty($arguments['pers_von'])) {
                        $arguments['pers_von'] = $arguments[$alias];
                        $aliasesApplied[] = "{$alias}→pers_von";
                    }
                    if (empty($arguments['pers_bis'])) {
                        $arguments['pers_bis'] = $arguments[$alias];
                        $aliasesApplied[] = "{$alias}→pers_bis";
                    }
                    break;
                }
            }

            $errors = [];
            if (empty($arguments['label'])) {
                $errors[] = $this->validationError('label', 'label ist erforderlich (z.B. "Tag 1" oder "20.03.2026").');
            }
            if (empty($arguments['datum'])) {
                $errors[] = $this->validationError('datum', 'datum ist erforderlich (YYYY-MM-DD).');
            }

            // day_type STRIKT gegen Settings → Tages-Typen.
            $allowedDayTypes = SettingsService::dayTypes($event->team_id);
            if (array_key_exists('day_type', $arguments) && $arguments['day_type'] !== null && $arguments['day_type'] !== '') {
                if (!in_array($arguments['day_type'], $allowedDayTypes, true)) {
                    $errors[] = $this->validationError(
                        'day_type',
                        'day_type "' . $arguments['day_type'] . '" ist nicht erlaubt. Erlaubt: '
                        . implode(' | ', array_map(fn ($v) => '"' . $v . '"', $allowedDayTypes))
                        . '. Erweiterbar in Einstellungen → Tages-Typen.'
                    );
                }
            }

            // color Format-Validation (nur wenn explizit gesetzt).
            $hasColor = array_key_exists('color', $arguments)
                && $arguments['color'] !== null && $arguments['color'] !== '';
            if ($hasColor && !preg_match(self::COLOR_REGEX, (string) $arguments['color'])) {
                $errors[] = $this->validationError(
                    'color',
                    'color muss Hex-Format haben: #RGB, #RRGGBB oder #RRGGBBAA (z.B. "#6366f1").'
                );
            }

            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }

            $known = [
                'event_id', 'event_uuid', 'event_number',
                'label', 'day_type', 'datum', 'day_of_week',
                'von', 'bis', 'pers_von', 'pers_bis',
                'day_status', 'color', 'sort_order',
                // Aliase
                'beginn', 'ende', 'start_time', 'end_time', 'start', 'end',
                'pers', 'pax', 'persons',
            ];
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
            $dow = $arguments['day_of_week'] ?? null;
            if (empty($dow)) {
                try {
                    $dow = $weekdays[Carbon::parse($arguments['datum'])->dayOfWeek];
                } catch (\Throwable $e) {
                    $dow = null;
                }
            }

            $maxSort = (int) EventDay::where('event_id', $event->id)->max('sort_order');

            // Color-Resolution: explizit > DB-Default (Spalte einfach weglassen).
            $colorSource = 'db_default';
            $payload = [
                'event_id'    => $event->id,
                'team_id'     => $event->team_id,
                'user_id'     => $context->user->id,
                'label'       => $arguments['label'],
                'day_type'    => $arguments['day_type'] ?? 'Veranstaltungstag',
                'datum'       => $arguments['datum'],
                'day_of_week' => $dow,
                'von'         => $arguments['von'] ?? null,
                'bis'         => $arguments['bis'] ?? null,
                'pers_von'    => $arguments['pers_von'] ?? null,
                'pers_bis'    => $arguments['pers_bis'] ?? null,
                'day_status'  => $arguments['day_status'] ?? 'Option',
                'sort_order'  => $maxSort + 1,
            ];
            if ($hasColor) {
                $payload['color'] = (string) $arguments['color'];
                $colorSource = 'explicit';
            }

            $day = EventDay::create($payload);

            return ToolResult::success([
                'id'             => $day->id,
                'uuid'           => $day->uuid,
                'event_id'       => $event->id,
                'label'          => $day->label,
                'day_type'       => $day->day_type,
                'datum'          => $day->datum?->toDateString(),
                'day_of_week'    => $day->day_of_week,
                'von'            => $day->von,
                'bis'            => $day->bis,
                'pers_von'       => $day->pers_von,
                'pers_bis'       => $day->pers_bis,
                'day_status'     => $day->day_status,
                'color'          => $day->color,
                'color_source'   => $colorSource,
                'sort_order'     => $day->sort_order,
                'aliases_applied'=> $aliasesApplied,
                'ignored_fields' => $ignored,
                'empty_recommended_field_options' => [
                    'day_type' => [
                        'values' => $allowedDayTypes,
                        'strict' => true,
                        'note'   => 'Strict gegen Settings. Erweiterbar in Einstellungen → Tages-Typen. Tippfehler werden abgelehnt.',
                    ],
                ],
                '_field_hints' => [
                    'day_type' => 'Wird beim Apply von Location-Pricings als day_type_label gematcht (Pricing → Tag). Konsistente Werte sind hier wichtig fuer Auto-Suggest.',
                ],
                'message'        => "Tag '{$day->label}' zu Event #{$event->event_number} hinzugefügt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen des Tages: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'day', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
