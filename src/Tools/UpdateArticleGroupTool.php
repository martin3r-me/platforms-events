<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticleGroup;

class UpdateArticleGroupTool implements ToolContract, ToolMetadataContract
{
    protected const STRING_FIELDS = ['name', 'color', 'erloeskonto_7', 'erloeskonto_19'];

    public function getName(): string
    {
        return 'events.article-groups.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/article-groups/{id} - Aktualisiert eine Artikelgruppe. '
            . 'Identifikation: group_id ODER uuid. '
            . 'Felder (alle optional): name, parent_id (Baumstruktur), color, '
            . 'erloeskonto_7, erloeskonto_19, is_active, sort_order.';
    }

    public function getSchema(): array
    {
        $props = [
            'group_id'   => ['type' => 'integer'],
            'uuid'       => ['type' => 'string'],
            'parent_id'  => ['type' => 'integer'],
            'is_active'  => ['type' => 'boolean'],
            'sort_order' => ['type' => 'integer'],
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
            $query = ArticleGroup::query();
            if (!empty($arguments['group_id'])) {
                $query->where('id', (int) $arguments['group_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'group_id oder uuid ist erforderlich.');
            }
            $group = $query->first();
            if (!$group) {
                return ToolResult::error('GROUP_NOT_FOUND', 'Artikelgruppe nicht gefunden.');
            }
            if (!$context->user->teams()->where('teams.id', $group->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf die Gruppe.');
            }

            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) $update[$f] = $arguments[$f];
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
                        return ToolResult::error('VALIDATION_ERROR', 'parent_id gehoert nicht zum Team.');
                    }
                    if ($parent->id === $group->id) {
                        return ToolResult::error('VALIDATION_ERROR', 'parent_id darf nicht auf die Gruppe selbst verweisen.');
                    }
                    $update['parent_id'] = $parent->id;
                }
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $known = array_merge(['group_id', 'uuid', 'parent_id', 'is_active', 'sort_order'], self::STRING_FIELDS);
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $group->update($update);

            return ToolResult::success([
                'id'             => $group->id,
                'uuid'           => $group->uuid,
                'name'           => $group->name,
                'parent_id'      => $group->parent_id,
                'is_active'      => (bool) $group->is_active,
                'updated_fields' => array_keys($update),
                'ignored_fields' => $ignored,
                'message'        => 'Gruppe aktualisiert.',
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
