<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Article;
use Platform\Events\Models\ArticleGroup;

class DeleteArticleGroupTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.article-groups.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/article-groups/{id} - Soft-Delete einer Artikelgruppe. '
            . 'Sicherheits-Check: Loescht NICHT, wenn die Gruppe noch aktive Artikel oder Children-Gruppen enthaelt '
            . '(force=true erzwingt; alle Children werden mit-soft-deleted, Artikel verlieren ihre Gruppen-Bindung).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_id' => ['type' => 'integer'],
                'uuid'     => ['type' => 'string'],
                'force'    => ['type' => 'boolean', 'description' => 'Default false. true = Loeschen erzwingen, auch wenn Children oder Artikel haengen.'],
            ],
        ];
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
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            $force = (bool) ($arguments['force'] ?? false);
            $articleCount = Article::where('article_group_id', $group->id)->count();
            $childCount   = ArticleGroup::where('parent_id', $group->id)->count();

            if (!$force && ($articleCount > 0 || $childCount > 0)) {
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    "Gruppe enthaelt {$articleCount} Artikel und {$childCount} Untergruppen. Mit force=true erzwingen."
                );
            }

            $id = $group->id;
            $name = $group->name;
            $group->delete();

            return ToolResult::success([
                'id'                 => $id,
                'name'               => $name,
                'forced'             => $force,
                'related_articles'   => $articleCount,
                'related_subgroups'  => $childCount,
                'message'            => "Gruppe '{$name}' geloescht (soft).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-group', 'delete'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'destructive', 'idempotent' => false, 'side_effects' => ['deletes'],
        ];
    }
}
