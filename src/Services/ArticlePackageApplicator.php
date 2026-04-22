<?php

namespace Platform\Events\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Platform\Events\Models\Article;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;

/**
 * Fuegt ein ArticlePackage als neue QuotePositions an einen QuoteItem an.
 * Vorlagen-Eintraege, die auf einen Artikel verweisen, werden dabei mit den
 * aktuellen Artikel-Werten angereichert (ek, mwst, gruppe etc.).
 */
class ArticlePackageApplicator
{
    /**
     * @return Collection<QuotePosition> - die neu erzeugten Positionen
     */
    public static function apply(ArticlePackage $package, QuoteItem $target): Collection
    {
        $package->loadMissing(['items' => fn($q) => $q->orderBy('sort_order')]);
        $target->loadMissing('eventDay');

        $teamId = $target->team_id ?? $target->eventDay?->team_id ?? $package->team_id;
        $maxSort = (int) QuotePosition::where('quote_item_id', $target->id)->max('sort_order');
        $created = collect();

        foreach ($package->items as $pi) {
            $article = $pi->article_id
                ? Article::with('group:id,name')->where('team_id', $teamId)->find($pi->article_id)
                : null;

            $name    = (string) ($pi->name ?? $article?->name ?? '');
            $gruppe  = (string) ($pi->gruppe ?? $article?->group?->name ?? '');
            $gebinde = (string) ($pi->gebinde ?? $article?->gebinde ?? '');
            $anz     = (string) ($pi->quantity ?? 1);
            $ek      = (float)  ($article->ek ?? 0);
            $preis   = (float)  ($pi->vk ?? $article?->vk ?? 0);
            $mwst    = (string) ($article?->mwst ?? '7%');
            $gesamt  = (float)  ($pi->gesamt ?: ((float) $anz) * $preis);

            $pos = QuotePosition::create([
                'team_id'       => $teamId,
                'user_id'       => Auth::id(),
                'quote_item_id' => $target->id,
                'gruppe'        => $gruppe,
                'name'          => $name,
                'anz'           => $anz,
                'gebinde'       => $gebinde,
                'basis_ek'      => $ek,
                'ek'            => $ek,
                'preis'         => $preis,
                'mwst'          => $mwst,
                'gesamt'        => $gesamt,
                'sort_order'    => ++$maxSort,
            ]);
            $created->push($pos);
        }

        return $created;
    }
}
