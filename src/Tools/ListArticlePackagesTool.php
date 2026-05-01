<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticlePackage;

class ListArticlePackagesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.article-packages.GET.list';
    }

    public function getDescription(): string
    {
        return 'GET /events/article-packages - Listet Artikel-Vorlagen (Pakete) eines Teams. '
            . 'Optional: search (string), is_active (default true), include_items (bool, default false).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'          => ['type' => 'integer'],
                'search'           => ['type' => 'string'],
                'is_active'        => ['type' => 'boolean'],
                'include_items'    => ['type' => 'boolean', 'description' => 'Default false. true = Items pro Paket mitliefern.'],
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

            $query = ArticlePackage::where('team_id', $teamId)->withCount('items');
            if (array_key_exists('is_active', $arguments)) {
                $query->where('is_active', (bool) $arguments['is_active']);
            } else {
                $query->where('is_active', true);
            }
            if (!empty($arguments['search'])) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $arguments['search']) . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)->orWhere('description', 'like', $like);
                });
            }

            $includeItems = (bool) ($arguments['include_items'] ?? false);
            if ($includeItems) {
                $query->with(['items' => fn ($q) => $q->orderBy('sort_order')]);
            }

            $packages = $query->orderBy('sort_order')->orderBy('name')->get();

            $items = $packages->map(function (ArticlePackage $p) use ($includeItems) {
                $row = [
                    'id'               => $p->id,
                    'uuid'             => $p->uuid,
                    'name'             => $p->name,
                    'description'      => $p->description,
                    'color'            => $p->color,
                    'is_active'        => (bool) $p->is_active,
                    'sort_order'       => $p->sort_order,
                    'items_count'      => (int) ($p->items_count ?? 0),
                ];
                if ($includeItems) {
                    $row['items'] = $p->items->map(fn ($i) => [
                        'id'         => $i->id,
                        'uuid'       => $i->uuid,
                        'article_id' => $i->article_id,
                        'name'       => $i->name,
                        'gruppe'     => $i->gruppe,
                        'quantity'   => (int) $i->quantity,
                        'gebinde'    => $i->gebinde,
                        'vk'         => (float) $i->vk,
                        'gesamt'     => (float) $i->gesamt,
                        'sort_order' => $i->sort_order,
                    ])->all();
                }
                return $row;
            })->all();

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
            'category' => 'query', 'tags' => ['events', 'article-package', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
