<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticleGroup;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Models\ArticlePackageItem;

class CreateArticlePackageTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.article-packages.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/article-packages - Legt eine Artikel-Vorlage (Paket) an. Pflicht: name. '
            . 'Felder: description, color (#RRGGBB), article_group_id (FK), is_active (default true), sort_order. '
            . 'Optional: items[] = Liste von Package-Items, die direkt mit angelegt werden – '
            . 'jedes Item: { article_id?, name, gruppe?, quantity (int, default 1), gebinde?, vk (decimal), gesamt (decimal optional) }. '
            . 'Wenn article_id angegeben ist, werden name/gebinde/vk aus dem Stammartikel uebernommen, sofern leer.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'          => ['type' => 'integer'],
                'name'             => ['type' => 'string'],
                'description'      => ['type' => 'string'],
                'color'            => ['type' => 'string'],
                'article_group_id' => ['type' => 'integer'],
                'is_active'        => ['type' => 'boolean'],
                'sort_order'       => ['type' => 'integer'],
                'items'            => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'article_id' => ['type' => 'integer'],
                            'name'       => ['type' => 'string'],
                            'gruppe'     => ['type' => 'string'],
                            'quantity'   => ['type' => 'integer'],
                            'gebinde'    => ['type' => 'string'],
                            'vk'         => ['type' => 'number'],
                            'gesamt'     => ['type' => 'number'],
                            'sort_order' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'required' => ['name'],
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
            if (empty($arguments['name'])) {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $groupId = null;
            if (!empty($arguments['article_group_id'])) {
                $group = ArticleGroup::where('team_id', $teamId)->find((int) $arguments['article_group_id']);
                if (!$group) {
                    return ToolResult::error('VALIDATION_ERROR', 'article_group_id gehoert nicht zum Team.');
                }
                $groupId = $group->id;
            }

            $maxSort = (int) ArticlePackage::where('team_id', $teamId)->max('sort_order');
            $package = ArticlePackage::create([
                'team_id'          => $teamId,
                'user_id'          => $context->user->id,
                'article_group_id' => $groupId,
                'name'             => $arguments['name'],
                'description'      => $arguments['description'] ?? null,
                'color'            => $arguments['color']       ?? null,
                'is_active'        => array_key_exists('is_active', $arguments) ? (bool) $arguments['is_active'] : true,
                'sort_order'       => $arguments['sort_order']  ?? $maxSort + 1,
            ]);

            // Items mitanlegen, wenn uebergeben.
            $createdItems = [];
            $itemsInput = $arguments['items'] ?? [];
            if (is_array($itemsInput) && !empty($itemsInput)) {
                $itemSort = 0;
                foreach ($itemsInput as $row) {
                    if (!is_array($row)) continue;

                    $articleId = !empty($row['article_id']) ? (int) $row['article_id'] : null;
                    $name      = (string) ($row['name'] ?? '');
                    $gebinde   = (string) ($row['gebinde'] ?? '');
                    $vk        = isset($row['vk']) ? (float) $row['vk'] : null;

                    // Stammartikel-Defaults uebernehmen
                    if ($articleId) {
                        $article = \Platform\Events\Models\Article::where('team_id', $teamId)->find($articleId);
                        if ($article) {
                            if ($name === '')    $name    = (string) $article->name;
                            if ($gebinde === '') $gebinde = (string) $article->gebinde;
                            if ($vk === null)    $vk      = (float)  $article->vk;
                        }
                    }
                    if ($name === '') {
                        continue; // ohne name kein Item
                    }
                    $quantity = isset($row['quantity']) ? (int) $row['quantity'] : 1;

                    $itemSort++;
                    $item = ArticlePackageItem::create([
                        'team_id'    => $teamId,
                        'user_id'    => $context->user->id,
                        'package_id' => $package->id,
                        'article_id' => $articleId,
                        'name'       => $name,
                        'gruppe'     => (string) ($row['gruppe'] ?? ''),
                        'quantity'   => $quantity,
                        'gebinde'    => $gebinde,
                        'vk'         => $vk ?? 0,
                        'gesamt'     => isset($row['gesamt']) ? (float) $row['gesamt'] : ($quantity * (float) ($vk ?? 0)),
                        'sort_order' => (int) ($row['sort_order'] ?? $itemSort),
                    ]);
                    $createdItems[] = ['id' => $item->id, 'uuid' => $item->uuid, 'name' => $item->name];
                }
            }

            return ToolResult::success([
                'id'             => $package->id,
                'uuid'           => $package->uuid,
                'name'           => $package->name,
                'items_created'  => count($createdItems),
                'items'          => $createdItems,
                'message'        => "Paket '{$package->name}' angelegt" . (count($createdItems) ? " (mit " . count($createdItems) . " Items)" : "") . ".",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-package', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'write', 'idempotent' => false, 'side_effects' => ['creates'],
        ];
    }
}
