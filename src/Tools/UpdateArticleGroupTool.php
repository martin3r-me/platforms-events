<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticleGroup;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;

class UpdateArticleGroupTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;

    protected const STRING_FIELDS = ['name', 'color', 'erloeskonto_7', 'erloeskonto_19'];

    /** Aliases analog Article/Booking-Tools (Naming-Bridge zur restlichen API). */
    protected const FIELD_ALIASES = [
        'article_group_id' => 'group_id',
    ];

    /** Erlaubte Formate: #RGB | #RRGGBB | #RRGGBBAA (case-insensitive). */
    protected const COLOR_REGEX = '/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/';

    public function getName(): string
    {
        return 'events.article-groups.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/article-groups/{id} - Aktualisiert eine Artikelgruppe. '
            . 'Identifikation: group_id (Alias article_group_id) ODER uuid. '
            . 'Felder (alle optional): name, parent_id (Baumstruktur), color (#RGB | #RRGGBB | #RRGGBBAA), '
            . 'erloeskonto_7, erloeskonto_19, is_active, sort_order.';
    }

    public function getSchema(): array
    {
        $props = [
            'group_id'         => ['type' => 'integer'],
            'article_group_id' => ['type' => 'integer', 'description' => 'Alias fuer group_id.'],
            'uuid'             => ['type' => 'string'],
            'parent_id'        => ['type' => 'integer'],
            'is_active'        => ['type' => 'boolean'],
            'sort_order'       => ['type' => 'integer'],
        ];
        foreach (self::STRING_FIELDS as $f) $props[$f] = ['type' => 'string'];
        return ['type' => 'object', 'properties' => $props];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            // Aliases mappen (article_group_id → group_id).
            $aliasesApplied = [];
            foreach (self::FIELD_ALIASES as $alias => $primary) {
                if (array_key_exists($alias, $arguments)
                    && (!array_key_exists($primary, $arguments) || $arguments[$primary] === null || $arguments[$primary] === '')
                ) {
                    $arguments[$primary] = $arguments[$alias];
                    $aliasesApplied[] = "{$alias}→{$primary}";
                }
            }

            $query = ArticleGroup::query();
            if (!empty($arguments['group_id'])) {
                $query->where('id', (int) $arguments['group_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'group_id (oder Alias article_group_id) oder uuid ist erforderlich.');
            }
            $group = $query->first();
            if (!$group) {
                return ToolResult::error('GROUP_NOT_FOUND', 'Artikelgruppe nicht gefunden.');
            }
            if (!$context->user->teams()->where('teams.id', $group->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf die Gruppe.');
            }

            // Strict-Validation (gebuendelt).
            $errors = [];
            if (array_key_exists('color', $arguments)
                && $arguments['color'] !== null && $arguments['color'] !== ''
                && !preg_match(self::COLOR_REGEX, (string) $arguments['color'])
            ) {
                $errors[] = $this->validationError(
                    'color',
                    'color muss Hex-Format haben: #RGB, #RRGGBB oder #RRGGBBAA (z.B. "#6366f1").'
                );
            }

            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $val = $arguments[$f];
                    // color darf nicht NULL gesetzt werden (Spalte ist NOT NULL mit DB-Default).
                    if ($f === 'color' && ($val === null || $val === '')) {
                        $errors[] = $this->validationError('color', 'color darf nicht leer sein.');
                        continue;
                    }
                    $update[$f] = $val === null ? null : (string) $val;
                }
            }
            if (array_key_exists('is_active', $arguments)) $update['is_active'] = (bool) $arguments['is_active'];
            if (array_key_exists('sort_order', $arguments)) $update['sort_order'] = (int) $arguments['sort_order'];
            if (array_key_exists('parent_id', $arguments)) {
                $pid = $arguments['parent_id'];
                if ($pid === null || $pid === '' || (int) $pid === 0) {
                    $update['parent_id'] = null;
                } else {
                    $parent = ArticleGroup::where('team_id', $group->team_id)->find((int) $pid);
                    if (!$parent) {
                        $errors[] = $this->validationError('parent_id', 'parent_id gehoert nicht zum Team.');
                    } elseif ($parent->id === $group->id) {
                        $errors[] = $this->validationError('parent_id', 'parent_id darf nicht auf die Gruppe selbst verweisen.');
                    } else {
                        $update['parent_id'] = $parent->id;
                    }
                }
            }

            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }
            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $known = array_merge(
                ['group_id', 'uuid', 'parent_id', 'is_active', 'sort_order'],
                self::STRING_FIELDS,
                array_keys(self::FIELD_ALIASES),
            );
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $group->update($update);

            return ToolResult::success([
                'id'              => $group->id,
                'uuid'            => $group->uuid,
                'name'            => $group->name,
                'parent_id'       => $group->parent_id,
                'color'           => $group->color,
                'is_active'       => (bool) $group->is_active,
                'sort_order'      => (int) $group->sort_order,
                'erloeskonto_7'   => $group->erloeskonto_7,
                'erloeskonto_19'  => $group->erloeskonto_19,
                'updated_fields'  => array_keys($update),
                'aliases_applied' => $aliasesApplied,
                'ignored_fields'  => $ignored,
                '_field_hints'    => [
                    'group_id' => 'Alias: article_group_id.',
                    'color'    => 'Hex-Format: #RGB | #RRGGBB | #RRGGBBAA. Nicht leer setzen.',
                ],
                'message'         => 'Gruppe aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-group', 'update'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
