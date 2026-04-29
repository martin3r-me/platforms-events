<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;

class UpdateEventDayTool implements ToolContract, ToolMetadataContract
{
    protected const FIELDS = [
        'label', 'day_type', 'datum', 'day_of_week', 'von', 'bis',
        'pers_von', 'pers_bis', 'day_status', 'color',
    ];

    public function getName(): string
    {
        return 'events.days.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/days/{id} - Aktualisiert einen Event-Tag. Identifikation: day_id ODER uuid. '
            . 'Felder (alle optional): '
            . 'label (string, z.B. "Tag 1"), '
            . 'day_type ("Veranstaltungstag" | "Aufbautag" | "Abbautag" | "Ruesttag"; weitere via Settings), '
            . 'datum (YYYY-MM-DD), day_of_week (So..Sa, sonst aus datum), '
            . 'von (HH:MM, Beginn), bis (HH:MM, Ende), '
            . 'pers_von (Personenzahl ab), pers_bis (Personenzahl bis), '
            . 'day_status ("Option" | "Definitiv" | "Vertrag" ...), '
            . 'color (#RRGGBB), sort_order (int).';
    }

    public function getSchema(): array
    {
        $props = [
            'day_id'     => ['type' => 'integer'],
            'uuid'       => ['type' => 'string'],
            'sort_order' => ['type' => 'integer'],
        ];
        foreach (self::FIELDS as $f) {
            $props[$f] = ['type' => 'string'];
        }
        return ['type' => 'object', 'properties' => $props];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = EventDay::query();
            if (!empty($arguments['day_id'])) {
                $query->where('id', (int) $arguments['day_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'day_id oder uuid ist erforderlich.');
            }

            $day = $query->first();
            if (!$day) {
                return ToolResult::error('DAY_NOT_FOUND', 'Der Event-Tag wurde nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $day->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Tag.');
            }

            $update = [];
            foreach (self::FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f];
                }
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int) $arguments['sort_order'];
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $day->update($update);

            return ToolResult::success([
                'id'      => $day->id,
                'uuid'    => $day->uuid,
                'label'   => $day->label,
                'datum'   => $day->datum?->toDateString(),
                'message' => "Tag '{$day->label}' aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Tages: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'day', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
