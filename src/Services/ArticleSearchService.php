<?php

namespace Platform\Events\Services;

use Illuminate\Support\Collection;
use Platform\Events\Models\Article;

/**
 * Team-gefilterte, performante Artikel-Suche mit Prefix-Priorisierung.
 * Hart begrenzt (LIMIT), damit auch bei 50k+ Artikeln ueber die Team-Spalte
 * und is_active in Millisekunden geantwortet wird.
 */
class ArticleSearchService
{
    /**
     * Sucht Artikel eines Teams anhand von Name, Art-Nr. oder Ext.-Code.
     *
     * @param int    $teamId
     * @param string $query     - ab 2 Zeichen wird gesucht, sonst leer
     * @param int    $limit     - Default 20
     * @param array  $fields    - zurueckgegebene Spalten
     * @return Collection<Article>
     */
    public static function search(int $teamId, string $query, int $limit = 20, array $fields = ['id','article_number','name','gebinde','ek','vk','mwst']): Collection
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return collect();
        }

        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $query);
        $like = '%' . $escaped . '%';
        $prefixLike = $escaped . '%';

        return Article::where('team_id', $teamId)
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('article_number', 'like', $like)
                    ->orWhere('external_code', 'like', $like);
            })
            ->orderByRaw(
                'CASE WHEN name LIKE ? THEN 0 WHEN article_number LIKE ? THEN 1 ELSE 2 END',
                [$prefixLike, $prefixLike]
            )
            ->orderBy('name')
            ->limit($limit)
            ->get($fields);
    }
}
