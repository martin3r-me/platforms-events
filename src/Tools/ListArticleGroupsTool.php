<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticleGroup;

class ListArticleGroupsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.article-groups.GET.list';
    }

    public function getDescription(): string
    {
        return 'GET /events/article-groups - Listet Artikel-Gruppen (Baumstruktur ueber parent_id). '
            . 'Optional: parent_id (int, null = Top-Level), is_active (default true), team_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'   => ['type' => 'integer'],
                'parent_id' => ['type' => 'integer', 'description' => 'Filter: nur Children dieses Parents. null = Top-Level.'],
                'is_active' => ['type' => 'boolean'],
            ],
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

            $query = ArticleGroup::where('team_id', $teamId);
            if (array_key_exists('parent_id', $arguments)) {
                if ($arguments['parent_id'] === null) {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', (int) $arguments['parent_id']);
                }
            }
            if (array_key_exists('is_active', $arguments)) {
                $query->where('is_active', (bool) $arguments['is_active']);
            } else {
                $query->where('is_active', true);
            }

            $groups = $query->orderBy('sort_order')->orderBy('name')->get();
            $items = $groups->map(fn (ArticleGroup $g) => [
                'id'             => $g->id,
                'uuid'           => $g->uuid,
                'parent_id'      => $g->parent_id,
                'name'           => $g->name,
                'color'          => $g->color,
                'erloeskonto_7'  => $g->erloeskonto_7,
                'erloeskonto_19' => $g->erloeskonto_19,
                'sort_order'     => $g->sort_order,
                'is_active'      => (bool) $g->is_active,
            ])->all();

            return ToolResult::success([
                'team_id' => $teamId,
                'count'   => count($items),
                'items'   => $items,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Listen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query', 'tags' => ['events', 'article-group', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
