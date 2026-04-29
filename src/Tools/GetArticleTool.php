<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Article;

class GetArticleTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.articles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/articles/{id} - Liefert einen Artikel. Identifikation: article_id ODER uuid ODER article_number.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'article_id'     => ['type' => 'integer'],
                'uuid'           => ['type' => 'string'],
                'article_number' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            $query = Article::query();
            if (!empty($arguments['article_id'])) {
                $query->where('id', (int) $arguments['article_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } elseif (!empty($arguments['article_number'])) {
                $query->where('article_number', $arguments['article_number']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'article_id, uuid oder article_number ist erforderlich.');
            }
            $a = $query->first();
            if (!$a) {
                return ToolResult::error('ARTICLE_NOT_FOUND', 'Artikel nicht gefunden.');
            }
            if (!$context->user->teams()->where('teams.id', $a->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf den Artikel.');
            }
            // Reverse-Lookup: Locations/Pricings, die diesen Artikel referenzieren.
            // Verknuepfung erfolgt ueber LocationPricing.article_number (1:N).
            $linkedPricings = [];
            $linkedPricingIds = [];
            $articleNumber = trim((string) $a->article_number);
            if ($articleNumber !== '' && class_exists('\\Platform\\Locations\\Models\\LocationPricing')) {
                try {
                    $pricings = \Platform\Locations\Models\LocationPricing::query()
                        ->where('article_number', $articleNumber)
                        ->with('location:id,name,kuerzel,team_id')
                        ->orderBy('day_type_label')
                        ->get();
                    foreach ($pricings as $p) {
                        // Team-Match: nur Pricings, deren Location demselben Team gehoert.
                        if ($p->location && (int) $p->location->team_id !== (int) $a->team_id) {
                            continue;
                        }
                        $linkedPricingIds[] = (int) $p->id;
                        $linkedPricings[] = [
                            'id'             => (int) $p->id,
                            'location_id'    => $p->location?->id,
                            'location_name'  => $p->location?->name,
                            'location_kuerzel' => $p->location?->kuerzel,
                            'day_type_label' => $p->day_type_label,
                            'label'          => $p->label,
                            'price_net'      => isset($p->price_net) ? (float) $p->price_net : null,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Soft-fail: Locations-Modul evtl. inkompatible Spalten – Liste bleibt leer.
                }
            }

            return ToolResult::success([
                'id'                  => $a->id,
                'uuid'                => $a->uuid,
                'team_id'             => $a->team_id,
                'article_group_id'    => $a->article_group_id,
                'article_number'      => $a->article_number,
                'external_code'       => $a->external_code,
                'name'                => $a->name,
                'description'         => $a->description,
                'offer_text'          => $a->offer_text,
                'invoice_text'        => $a->invoice_text,
                'gebinde'             => $a->gebinde,
                'ek'                  => (float) $a->ek,
                'vk'                  => (float) $a->vk,
                'mwst'                => $a->mwst,
                'erloeskonto'         => $a->erloeskonto,
                'effective_erloeskonto' => $a->effective_erloeskonto,
                'lagerort'            => $a->lagerort,
                'min_bestand'         => $a->min_bestand,
                'current_bestand'     => $a->current_bestand,
                'is_active'           => (bool) $a->is_active,
                'sort_order'          => $a->sort_order,
                'procurement_type'    => $a->procurement_type,
                'linked_pricing_ids'  => $linkedPricingIds,
                'linked_pricings'     => $linkedPricings,
                '_field_hints'        => [
                    'linked_pricings'    => 'Reverse-Lookup ueber LocationPricing.article_number = article_number. Soft-Dependency auf platforms-locations.',
                    'linked_pricing_ids' => 'Kompaktes Array der Pricing-IDs (= linked_pricings[*].id).',
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query', 'tags' => ['events', 'article', 'get'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
