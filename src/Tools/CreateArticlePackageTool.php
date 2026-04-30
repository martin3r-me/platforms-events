<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticleGroup;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Models\ArticlePackageItem;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;

class CreateArticlePackageTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;

    /** Erlaubte Formate: #RGB | #RRGGBB | #RRGGBBAA (case-insensitive). */
    protected const COLOR_REGEX = '/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/';

    public function getName(): string
    {
        return 'events.article-packages.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/article-packages - Legt eine Artikel-Vorlage (Paket) an. Pflicht: name. '
            . 'Felder: description, color (#RGB | #RRGGBB | #RRGGBBAA; wenn nicht gesetzt: erbt von '
            . 'article_group_id.color, sonst DB-Default), article_group_id (FK), is_active (default true), sort_order. '
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
            // Strict-Validation (gebuendelt).
            $errors = [];
            if (empty($arguments['name'])) {
                $errors[] = $this->validationError('name', 'name ist erforderlich.');
            }
            $hasColor = array_key_exists('color', $arguments)
                && $arguments['color'] !== null && $arguments['color'] !== '';
            if ($hasColor && !preg_match(self::COLOR_REGEX, (string) $arguments['color'])) {
                $errors[] = $this->validationError(
                    'color',
                    'color muss Hex-Format haben: #RGB, #RRGGBB oder #RRGGBBAA (z.B. "#8b5cf6").'
                );
            }

            $group = null;
            if (!empty($arguments['article_group_id'])) {
                $group = ArticleGroup::where('team_id', $teamId)->find((int) $arguments['article_group_id']);
                if (!$group) {
                    $errors[] = $this->validationError('article_group_id', 'article_group_id gehoert nicht zum Team.');
                }
            }
            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }

            $maxSort = (int) ArticlePackage::where('team_id', $teamId)->max('sort_order');

            // Color-Resolution: explizit > group.color > DB-Default (Spalte einfach weglassen).
            $colorSource = 'db_default';
            $payload = [
                'team_id'          => $teamId,
                'user_id'          => $context->user->id,
                'article_group_id' => $group?->id,
                'name'             => $arguments['name'],
                'description'      => $arguments['description'] ?? null,
                'is_active'        => array_key_exists('is_active', $arguments) ? (bool) $arguments['is_active'] : true,
                'sort_order'       => $arguments['sort_order']  ?? $maxSort + 1,
            ];
            if ($hasColor) {
                $payload['color'] = (string) $arguments['color'];
                $colorSource = 'explicit';
            } elseif ($group && $group->color) {
                $payload['color'] = $group->color;
                $colorSource = 'inherited_from_group';
            }

            $package = ArticlePackage::create($payload);

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

            $known = [
                'team_id', 'name', 'description', 'color', 'article_group_id',
                'is_active', 'sort_order', 'items',
            ];
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $emptyRecommended = [];
            if (!$package->article_group_id) {
                $emptyRecommended['article_group_id'] = 'Artikelgruppe (FK) – ohne Gruppe greifen Vererbungen wie Erloeskonto/color nicht.';
            }
            if (!$package->description) {
                $emptyRecommended['description'] = 'Kurzbeschreibung der Vorlage (intern).';
            }

            return ToolResult::success([
                'id'                => $package->id,
                'uuid'              => $package->uuid,
                'name'              => $package->name,
                'description'       => $package->description,
                'color'             => $package->color,
                'color_source'      => $colorSource,
                'article_group_id'  => $package->article_group_id,
                'is_active'         => (bool) $package->is_active,
                'sort_order'        => (int) $package->sort_order,
                'items_created'     => count($createdItems),
                'items'             => $createdItems,
                'aliases_applied'   => [],
                'ignored_fields'    => $ignored,
                'empty_recommended_fields' => $emptyRecommended,
                '_field_hints'      => [
                    'color' => 'Hex-Format. Wenn weggelassen: erbt von article_group_id.color, sonst DB-Default.',
                    'items' => 'Bulk-Anlage von Package-Items moeglich. Einzeln auch via events.article-package-items.POST.',
                ],
                'message'           => "Paket '{$package->name}' angelegt" . (count($createdItems) ? " (mit " . count($createdItems) . " Items)" : "") . ".",
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
