<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ScheduleItem;

class UpdateScheduleItemTool implements ToolContract, ToolMetadataContract
{
    protected const STRING_FIELDS = ['datum', 'von', 'bis', 'beschreibung', 'raum', 'bemerkung'];

    public function getName(): string
    {
        return 'events.schedule-items.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/schedule/{id} - Aktualisiert einen Ablauf-Eintrag. Identifikation: schedule_id ODER uuid.';
    }

    public function getSchema(): array
    {
        $props = [
            'schedule_id' => ['type' => 'integer'],
            'uuid'        => ['type' => 'string'],
            'linked'      => ['type' => 'boolean'],
            'sort_order'  => ['type' => 'integer'],
        ];
        foreach (self::STRING_FIELDS as $f) {
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

            $query = ScheduleItem::query();
            if (!empty($arguments['schedule_id'])) {
                $query->where('id', (int) $arguments['schedule_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'schedule_id oder uuid ist erforderlich.');
            }

            $item = $query->first();
            if (!$item) {
                return ToolResult::error('SCHEDULE_NOT_FOUND', 'Ablauf-Eintrag nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $item->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f];
                }
            }
            if (array_key_exists('linked', $arguments)) {
                $update['linked'] = (bool) $arguments['linked'];
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int) $arguments['sort_order'];
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $item->update($update);

            return ToolResult::success([
                'id'           => $item->id,
                'uuid'         => $item->uuid,
                'beschreibung' => $item->beschreibung,
                'message'      => 'Ablauf-Eintrag aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'schedule', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
