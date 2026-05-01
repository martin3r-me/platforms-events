<?php

namespace Platform\Events\Services;

use Illuminate\Support\Collection;
use Platform\Core\Contracts\CatalogArticleSearchProviderInterface;

/**
 * Team-gefilterte Artikel-Suche. Delegiert an den CatalogArticleSearchProvider
 * (Commerce-Implementierung oder Null-Fallback).
 */
class ArticleSearchService
{
    public static function search(int $teamId, string $query, int $limit = 20): Collection
    {
        return app(CatalogArticleSearchProviderInterface::class)
            ->search($teamId, $query, $limit);
    }
}
