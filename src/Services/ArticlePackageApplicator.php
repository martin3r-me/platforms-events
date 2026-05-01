<?php

namespace Platform\Events\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\CatalogArticleResolverInterface;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;
use Platform\Events\Services\PositionValidator;

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

        $resolver = app(CatalogArticleResolverInterface::class);

        foreach ($package->items as $pi) {
            $article = $pi->article_id
                ? $resolver->resolve($pi->article_id, $teamId)
                : null;

            $payload = [
                'name'    => (string) ($pi->name ?? ($article['name'] ?? '')),
                'gruppe'  => (string) ($pi->gruppe ?? ($article['category_name'] ?? '')),
                'gebinde' => (string) ($pi->gebinde ?? ($article['gebinde'] ?? '')),
                'anz'     => (string) ($pi->quantity ?? 1),
                'ek'      => (float)  ($article['ek'] ?? 0),
                'preis'   => (float)  ($pi->vk ?? ($article['vk'] ?? 0)),
                'mwst'    => (string) ($article['mwst'] ?? '7%'),
            ];
            $payload['gesamt'] = (float) ($pi->gesamt ?: ((float) $payload['anz']) * $payload['preis']);

            // Package-Items ohne bzw. mit unbekannter Gruppe muessen sichtbar
            // scheitern — sonst entstehen hier genau die Buchhaltungs-Chaos-
            // Positionen, die die addPosition-Validierung verhindern soll.
            if ($err = PositionValidator::validate($payload, PositionValidator::allowedGruppen($teamId))) {
                throw new \RuntimeException(
                    'Paket "' . $package->name . '", Position "' . ($payload['name'] ?: '—') . '": ' . $err
                );
            }

            $pos = QuotePosition::create([
                'team_id'       => $teamId,
                'user_id'       => Auth::id(),
                'quote_item_id' => $target->id,
                'gruppe'        => $payload['gruppe'],
                'name'          => $payload['name'],
                'anz'           => $payload['anz'],
                'gebinde'       => $payload['gebinde'],
                'basis_ek'      => $payload['ek'],
                'ek'            => $payload['ek'],
                'preis'         => $payload['preis'],
                'mwst'          => $payload['mwst'],
                'gesamt'        => $payload['gesamt'],
                'sort_order'    => ++$maxSort,
            ]);
            $created->push($pos);
        }

        return $created;
    }
}
