<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticleGroup;
use Platform\Events\Models\ArticlePackage;

class UpdateArticlePackageTool implements ToolContract, ToolMetadataContract
{
    protected const STRING_FIELDS = ['name', 'description', 'color'];

    public function getName(): string
    {
        return 'events.article-packages.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/article-packages/{id} - Aktualisiert ein Paket. '
            . 'Identifikation: package_id ODER uuid. '
            . 'Felder: name, description, color, article_group_id, is_active, sort_order.';
    }

    public function getSchema(): array
    {
        $props = [
            'package_id'       => ['type' => 'integer'],
            'uuid'             => ['type' => 'string'],
            'article_group_id' => ['type' => 'integer'],
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
            $query = ArticlePackage::query();
            if (!empty($arguments['package_id'])) {
                $query->where('id', (int) $arguments['package_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'package_id oder uuid ist erforderlich.');
            }
            $package = $query->first();
            if (!$package) {
                return ToolResult::error('PACKAGE_NOT_FOUND', 'Paket nicht gefunden.');
            }
            if (!$context->user->teams()->where('teams.id', $package->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) $update[$f] = $arguments[$f];
            }
            if (array_key_exists('is_active', $arguments)) $update['is_active'] = (bool) $arguments['is_active'];
            if (array_key_exists('sort_order', $arguments)) $update['sort_order'] = (int) $arguments['sort_order'];
            if (array_key_exists('article_group_id', $arguments)) {
                $gid = $arguments['article_group_id'];
                if ($gid === null || $gid === '' || (int) $gid === 0) {
                    $update['article_group_id'] = null;
                } else {
                    $group = ArticleGroup::where('team_id', $package->team_id)->find((int) $gid);
                    if (!$group) {
                        return ToolResult::error('VALIDATION_ERROR', 'article_group_id gehoert nicht zum Team.');
                    }
                    $update['article_group_id'] = $group->id;
                }
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $known = array_merge(['package_id', 'uuid', 'article_group_id', 'is_active', 'sort_order'], self::STRING_FIELDS);
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $package->update($update);

            return ToolResult::success([
                'id'             => $package->id,
                'uuid'           => $package->uuid,
                'name'           => $package->name,
                'is_active'      => (bool) $package->is_active,
                'updated_fields' => array_keys($update),
                'ignored_fields' => $ignored,
                'message'        => 'Paket aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-package', 'update'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
