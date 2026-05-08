<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\MrFieldConfig;

/**
 * Liefert die Management-Report-Feldkonfiguration eines Teams ohne Event-Kontext.
 * Damit kann ein Aufrufer (LLM/UI) vorab erfahren, welche mr_data-Keys + Werte
 * beim POST/PATCH eines Events erlaubt sind, ohne erst ein konkretes Event laden zu muessen.
 */
class GetMrFieldConfigTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.settings.mr_data.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/settings/mr_data - Liefert die Management-Report-Felder + Optionen eines Teams (Lookup ohne Event-Kontext). '
            . 'Default: aktuelles Team aus Kontext. Response enthaelt fields[] mit {label, mrf_key, group_label, options[], is_active, sort_order} '
            . 'und allowed_values_by_label-Map fuer schnelle Validierung. Alle Werte sind Single-Source aus den Settings (Einstellungen → Management Report).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'      => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.'],
                'only_active'  => ['type' => 'boolean', 'description' => 'Optional: nur aktive Felder zurueckgeben. Default true.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
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

            $userHasAccess = $context->user->teams()->where('teams.id', (int) $teamId)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', "Du hast keinen Zugriff auf Team-ID {$teamId}.");
            }

            $onlyActive = array_key_exists('only_active', $arguments) ? (bool) $arguments['only_active'] : true;

            $query = MrFieldConfig::where('team_id', (int) $teamId);
            if ($onlyActive) {
                $query->where('is_active', true);
            }
            $configs = $query->orderBy('sort_order')->get();

            $fields = [];
            $allowedByLabel = [];
            foreach ($configs as $cfg) {
                $values = array_values(array_map(
                    fn ($o) => is_array($o) ? ($o['label'] ?? '') : (string) $o,
                    $cfg->options ?? []
                ));
                $fields[] = [
                    'label'       => $cfg->label,
                    'mrf_key'     => 'mrf_' . $cfg->id,
                    'group_label' => $cfg->group_label,
                    'options'     => $values,
                    'is_active'   => (bool) $cfg->is_active,
                    'sort_order'  => (int) $cfg->sort_order,
                ];
                $allowedByLabel[$cfg->label] = $values;
            }

            return ToolResult::success([
                'team_id'                => (int) $teamId,
                'fields'                 => $fields,
                'allowed_keys'           => array_keys($allowedByLabel),
                'allowed_values_by_label'=> $allowedByLabel,
                'count'                  => count($fields),
                'note'                   => 'Pflegen / sortieren in Einstellungen → Management Report. Werte sind verbindlich – mr_data am Event akzeptiert nur Keys + Werte aus dieser Liste.',
                'message'                => count($fields) . ' MR-Feld(er) konfiguriert fuer Team ' . $teamId . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der MR-Feldkonfiguration: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['events', 'settings', 'management-report'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'read',
            'idempotent'    => true,
            'side_effects'  => [],
        ];
    }
}
