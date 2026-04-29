<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticleGroup;

class CreateArticleGroupTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.article-groups.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/article-groups - Legt eine Artikelgruppe an. Pflicht: name. '
            . 'Optional: parent_id (FK fuer Baumstruktur), color (#RRGGBB), '
            . 'erloeskonto_7 / erloeskonto_19 (Default-Konten fuer 7%/19% MwSt – '
            . 'vererben sich auf Artikel ohne eigenes erloeskonto), '
            . 'is_active (bool, default true), sort_order.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'        => ['type' => 'integer'],
                'name'           => ['type' => 'string'],
                'parent_id'      => ['type' => 'integer'],
                'color'          => ['type' => 'string'],
                'erloeskonto_7'  => ['type' => 'string'],
                'erloeskonto_19' => ['type' => 'string'],
                'is_active'      => ['type' => 'boolean'],
                'sort_order'     => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            $teamId = (int) ($arguments['team_id'] ?? $context->team?->id ?? 0);
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team gefunden.');
            }
            if (!$context->user->teams()->where('teams.id', $teamId)->exists()) {
                return ToolResult::error('ACCESS_DENIED', "Kein Zugriff auf Team-ID {$teamId}.");
            }
            if (empty($arguments['name'])) {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $parentId = null;
            if (!empty($arguments['parent_id'])) {
                $parent = ArticleGroup::where('team_id', $teamId)->find((int) $arguments['parent_id']);
                if (!$parent) {
                    return ToolResult::error('VALIDATION_ERROR', 'parent_id gehoert nicht zum Team.');
                }
                $parentId = $parent->id;
            }

            $maxSort = (int) ArticleGroup::where('team_id', $teamId)
                ->when($parentId, fn ($q) => $q->where('parent_id', $parentId), fn ($q) => $q->whereNull('parent_id'))
                ->max('sort_order');

            $group = ArticleGroup::create([
                'team_id'        => $teamId,
                'user_id'        => $context->user->id,
                'parent_id'      => $parentId,
                'name'           => $arguments['name'],
                'color'          => $arguments['color']          ?? null,
                'erloeskonto_7'  => $arguments['erloeskonto_7']  ?? null,
                'erloeskonto_19' => $arguments['erloeskonto_19'] ?? null,
                'is_active'      => array_key_exists('is_active', $arguments) ? (bool) $arguments['is_active'] : true,
                'sort_order'     => $arguments['sort_order']     ?? $maxSort + 1,
            ]);

            return ToolResult::success([
                'id'        => $group->id,
                'uuid'      => $group->uuid,
                'name'      => $group->name,
                'parent_id' => $group->parent_id,
                'message'   => "Gruppe '{$group->name}' angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-group', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'write', 'idempotent' => false, 'side_effects' => ['creates'],
        ];
    }
}
