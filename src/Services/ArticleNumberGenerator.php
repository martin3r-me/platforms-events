<?php

namespace Platform\Events\Services;

use Platform\Events\Models\Article;

/**
 * Erzeugt eine eindeutige article_number pro Team.
 * Schema: ART-{teamId}-{seq}, wobei seq die naechste Nummer pro Team ist.
 * Soft-deleted Artikel werden mitgezaehlt, damit der Unique-Constraint
 * (team_id, article_number) nicht verletzt wird.
 */
class ArticleNumberGenerator
{
    public static function next(int $teamId): string
    {
        $prefix = 'ART-' . $teamId . '-';
        $last = Article::withTrashed()
            ->where('team_id', $teamId)
            ->where('article_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(article_number) DESC, article_number DESC')
            ->value('article_number');

        $nextSeq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . $nextSeq;
    }
}
