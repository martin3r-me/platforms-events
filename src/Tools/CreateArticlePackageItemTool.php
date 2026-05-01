<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\CatalogArticleResolverInterface;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Models\ArticlePackageItem;

class CreateArticlePackageItemTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.article-package-items.POST';
    }

    /** Aliases analog zur restlichen API. */
    protected const FIELD_ALIASES = [
        'article_package_id'   => 'package_id',
        'article_package_uuid' => 'package_uuid',
    ];

    public function getDescription(): string
    {
        return 'POST /events/article-packages/{package}/items - Fuegt ein Item zu einem Paket hinzu. '
            . 'Pflicht: package_id|package_uuid (Aliase: article_package_id|article_package_uuid) + name '
            . '(oder article_id, dann wird name aus Stammartikel uebernommen). '
            . 'Felder: article_id, name, gruppe, quantity (default 1), gebinde, vk, gesamt (default quantity*vk), sort_order. '
            . 'Bulk-POST mit items[] auch ueber CreateArticlePackageTool moeglich.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'package_id'           => ['type' => 'integer'],
                'package_uuid'         => ['type' => 'string'],
                'article_package_id'   => ['type' => 'integer', 'description' => 'Alias fuer package_id.'],
                'article_package_uuid' => ['type' => 'string',  'description' => 'Alias fuer package_uuid.'],
                'article_id'           => ['type' => 'integer'],
                'name'                 => ['type' => 'string'],
                'gruppe'                => ['type' => 'string'],
                'quantity'              => ['type' => 'integer'],
                'gebinde'               => ['type' => 'string'],
                'vk'                    => ['type' => 'number'],
                'gesamt'                => ['type' => 'number'],
                'sort_order'            => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            // Aliases mappen (article_package_id → package_id, article_package_uuid → package_uuid).
            $aliasesApplied = [];
            foreach (self::FIELD_ALIASES as $alias => $primary) {
                if (array_key_exists($alias, $arguments)
                    && (!array_key_exists($primary, $arguments) || $arguments[$primary] === null || $arguments[$primary] === '')
                ) {
                    $arguments[$primary] = $arguments[$alias];
                    $aliasesApplied[] = "{$alias}→{$primary}";
                }
            }

            $package = null;
            if (!empty($arguments['package_id'])) {
                $package = ArticlePackage::find((int) $arguments['package_id']);
            } elseif (!empty($arguments['package_uuid'])) {
                $package = ArticlePackage::where('uuid', $arguments['package_uuid'])->first();
            }
            if (!$package) {
                return ToolResult::error('VALIDATION_ERROR', 'package_id oder package_uuid (Aliase: article_package_id|article_package_uuid) ist erforderlich.');
            }
            if (!$context->user->teams()->where('teams.id', $package->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf das Paket.');
            }

            $articleId = !empty($arguments['article_id']) ? (int) $arguments['article_id'] : null;
            $name = (string) ($arguments['name'] ?? '');
            $gebinde = (string) ($arguments['gebinde'] ?? '');
            $vk = isset($arguments['vk']) ? (float) $arguments['vk'] : null;

            if ($articleId) {
                $article = app(CatalogArticleResolverInterface::class)->resolve($articleId, $package->team_id);
                if (!$article) {
                    return ToolResult::error('VALIDATION_ERROR', 'article_id nicht gefunden oder gehoert nicht zum Team.');
                }
                if ($name === '')    $name    = (string) $article['name'];
                if ($gebinde === '') $gebinde = (string) ($article['gebinde'] ?? '');
                if ($vk === null)    $vk      = (float)  ($article['vk'] ?? 0);
            }
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich (oder article_id setzen, damit der Name aus dem Stammartikel uebernommen wird).');
            }
            $quantity = isset($arguments['quantity']) ? (int) $arguments['quantity'] : 1;
            $vk = $vk ?? 0;
            $gesamt = isset($arguments['gesamt']) ? (float) $arguments['gesamt'] : $quantity * $vk;

            $maxSort = (int) ArticlePackageItem::where('package_id', $package->id)->max('sort_order');

            $item = ArticlePackageItem::create([
                'team_id'    => $package->team_id,
                'user_id'    => $context->user->id,
                'package_id' => $package->id,
                'article_id' => $articleId,
                'name'       => $name,
                'gruppe'     => (string) ($arguments['gruppe'] ?? ''),
                'quantity'   => $quantity,
                'gebinde'    => $gebinde,
                'vk'         => $vk,
                'gesamt'     => $gesamt,
                'sort_order' => $arguments['sort_order'] ?? ($maxSort + 1),
            ]);

            $known = [
                'package_id', 'package_uuid', 'article_id',
                'name', 'gruppe', 'quantity', 'gebinde', 'vk', 'gesamt', 'sort_order',
            ];
            $known = array_merge($known, array_keys(self::FIELD_ALIASES));
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            return ToolResult::success([
                'id'              => $item->id,
                'uuid'            => $item->uuid,
                'package_id'      => $package->id,
                'name'            => $item->name,
                'quantity'        => (int) $item->quantity,
                'vk'              => (float) $item->vk,
                'gesamt'          => (float) $item->gesamt,
                'aliases_applied' => $aliasesApplied,
                'ignored_fields'  => $ignored,
                'message'         => "Item '{$item->name}' zu Paket '{$package->name}' hinzugefuegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-package-item', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => false, 'side_effects' => ['creates'],
        ];
    }
}
