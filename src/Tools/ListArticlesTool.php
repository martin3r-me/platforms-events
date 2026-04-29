<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Article;
use Platform\Events\Services\ArticleSearchService;

/**
 * Listet Artikel-Stammdaten. Mit search-Parameter: nutzt ArticleSearchService
 * fuer Volltextsuche; ohne search: einfache Filter (group, is_active).
 */
class ListArticlesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.articles.GET.list';
    }

    public function getDescription(): string
    {
        return 'GET /events/articles - Listet Artikel-Stammdaten. '
            . 'Optional: search (string, Volltext über article_number/name/description), '
            . 'article_group_id (int, Filter), is_active (bool, Default true), '
            . 'limit (int, Default 50, max 200), team_id (int, Default: aktuelles Team), '
            . 'include_linked_pricings (bool, Default false – pro Artikel kompakte Liste linked_pricing_ids[] '
            . 'aus platforms-locations via article_number-Match).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'          => ['type' => 'integer'],
                'search'           => ['type' => 'string',  'description' => 'Volltext-Query (article_number / name / description).'],
                'article_group_id' => ['type' => 'integer', 'description' => 'Filter: nur Artikel dieser Gruppe.'],
                'is_active'        => ['type' => 'boolean', 'description' => 'Default true.'],
                'limit'            => ['type' => 'integer', 'description' => 'Default 50, max 200.'],
                'include_linked_pricings' => ['type' => 'boolean', 'description' => 'Default false. true = pro Artikel linked_pricing_ids[] aus LocationPricing per article_number-Match.'],
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

            $limit = max(1, min((int) ($arguments['limit'] ?? 50), 200));
            $search = trim((string) ($arguments['search'] ?? ''));

            if ($search !== '') {
                $rows = ArticleSearchService::search($teamId, $search, $limit, [
                    'id', 'uuid', 'article_number', 'name', 'description', 'gebinde', 'ek', 'vk', 'mwst',
                    'article_group_id', 'is_active', 'procurement_type',
                ]);
            } else {
                $query = Article::where('team_id', $teamId);
                if (array_key_exists('is_active', $arguments)) {
                    $query->where('is_active', (bool) $arguments['is_active']);
                } else {
                    $query->where('is_active', true);
                }
                if (!empty($arguments['article_group_id'])) {
                    $query->where('article_group_id', (int) $arguments['article_group_id']);
                }
                $rows = $query->orderBy('sort_order')->orderBy('name')->limit($limit)->get();
            }

            // Optionaler Reverse-Lookup: linked_pricing_ids[] pro Artikel.
            $includeLinkedPricings = (bool) ($arguments['include_linked_pricings'] ?? false);
            $linkedMap = []; // article_number => [pricing_id, ...]
            if ($includeLinkedPricings && class_exists('\\Platform\\Locations\\Models\\LocationPricing')) {
                $articleNumbers = $rows->pluck('article_number')->filter()->unique()->values()->all();
                if (!empty($articleNumbers)) {
                    try {
                        $pricingsByNumber = \Platform\Locations\Models\LocationPricing::query()
                            ->whereIn('article_number', $articleNumbers)
                            ->whereHas('location', fn ($q) => $q->where('team_id', $teamId))
                            ->get(['id', 'article_number']);
                        foreach ($pricingsByNumber as $p) {
                            $linkedMap[(string) $p->article_number][] = (int) $p->id;
                        }
                    } catch (\Throwable $e) {
                        // Soft-fail – Map bleibt leer.
                    }
                }
            }

            $items = $rows->map(function (Article $a) use ($includeLinkedPricings, $linkedMap) {
                $row = [
                    'id'              => $a->id,
                    'uuid'            => $a->uuid,
                    'article_number'  => $a->article_number,
                    'name'            => $a->name,
                    'description'    => $a->description,
                    'gebinde'         => $a->gebinde,
                    'ek'              => (float) $a->ek,
                    'vk'              => (float) $a->vk,
                    'mwst'            => $a->mwst,
                    'article_group_id'=> $a->article_group_id,
                    'procurement_type'=> $a->procurement_type,
                    'is_active'       => (bool) $a->is_active,
                ];
                if ($includeLinkedPricings) {
                    $row['linked_pricing_ids'] = $linkedMap[(string) $a->article_number] ?? [];
                }
                return $row;
            })->all();

            return ToolResult::success([
                'team_id' => $teamId,
                'count'   => count($items),
                'limit'   => $limit,
                'search'  => $search,
                'items'   => $items,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Listen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query', 'tags' => ['events', 'article', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
