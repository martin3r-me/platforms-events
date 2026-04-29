<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Article;
use Platform\Events\Models\ArticleGroup;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;

class CreateArticleTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;

    protected const PROCUREMENT_TYPES = ['stock', 'supplier', 'kitchen'];

    public function getName(): string
    {
        return 'events.articles.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/articles - Legt einen Artikel-Stammdatensatz an. '
            . 'Pflicht: name, article_group_id (FK events_article_groups.id). '
            . 'Felder: '
            . 'article_number (string, default leer), external_code (string, z.B. WaWi-Nummer), '
            . 'description, offer_text (Text fuer Angebot), invoice_text (Text fuer Rechnung), '
            . 'gebinde (z.B. "1 Port."), ek (decimal, EK-Preis), vk (decimal, VK-Preis), '
            . 'mwst (string: "0%" | "7%" | "19%"; default "7%"), erloeskonto (string, ueberschreibt Group-Default), '
            . 'lagerort, min_bestand (int), current_bestand (int), '
            . 'procurement_type ("stock" | "supplier" | "kitchen"; default "stock"), '
            . 'is_active (bool, default true), sort_order (int).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'          => ['type' => 'integer'],
                'name'             => ['type' => 'string'],
                'article_group_id' => ['type' => 'integer'],
                'article_number'   => ['type' => 'string'],
                'external_code'    => ['type' => 'string'],
                'description'      => ['type' => 'string'],
                'offer_text'       => ['type' => 'string'],
                'invoice_text'     => ['type' => 'string'],
                'gebinde'          => ['type' => 'string'],
                'ek'               => ['type' => 'number'],
                'vk'               => ['type' => 'number'],
                'mwst'             => ['type' => 'string', 'enum' => ['0%', '7%', '19%']],
                'erloeskonto'      => ['type' => 'string'],
                'lagerort'         => ['type' => 'string'],
                'min_bestand'      => ['type' => 'integer'],
                'current_bestand'  => ['type' => 'integer'],
                'procurement_type' => ['type' => 'string', 'enum' => self::PROCUREMENT_TYPES],
                'is_active'        => ['type' => 'boolean'],
                'sort_order'       => ['type' => 'integer'],
            ],
            'required' => ['name', 'article_group_id'],
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

            $errors = [];
            if (empty($arguments['name'])) {
                $errors[] = $this->validationError('name', 'name ist erforderlich.');
            }
            if (empty($arguments['article_group_id'])) {
                $errors[] = $this->validationError('article_group_id', 'article_group_id ist erforderlich.');
            }
            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }

            $group = ArticleGroup::where('team_id', $teamId)->find((int) $arguments['article_group_id']);
            if (!$group) {
                return ToolResult::error('VALIDATION_ERROR', 'article_group_id gehoert nicht zum Team oder existiert nicht.');
            }

            $procurementType = $arguments['procurement_type'] ?? 'stock';
            if (!in_array($procurementType, self::PROCUREMENT_TYPES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'procurement_type muss einer von: ' . implode(', ', self::PROCUREMENT_TYPES));
            }

            $maxSort = (int) Article::where('team_id', $teamId)
                ->where('article_group_id', $group->id)
                ->max('sort_order');

            $article = Article::create([
                'team_id'          => $teamId,
                'user_id'          => $context->user->id,
                'article_group_id' => $group->id,
                'name'             => $arguments['name'],
                'article_number'   => $arguments['article_number']  ?? '',
                'external_code'    => $arguments['external_code']   ?? null,
                'description'      => $arguments['description']     ?? null,
                'offer_text'       => $arguments['offer_text']      ?? null,
                'invoice_text'     => $arguments['invoice_text']    ?? null,
                'gebinde'          => $arguments['gebinde']         ?? null,
                'ek'               => isset($arguments['ek']) ? (float) $arguments['ek'] : 0,
                'vk'               => isset($arguments['vk']) ? (float) $arguments['vk'] : 0,
                'mwst'             => $arguments['mwst']            ?? '7%',
                'erloeskonto'      => $arguments['erloeskonto']     ?? null,
                'lagerort'         => $arguments['lagerort']        ?? null,
                'min_bestand'      => $arguments['min_bestand']     ?? null,
                'current_bestand'  => $arguments['current_bestand'] ?? null,
                'procurement_type' => $procurementType,
                'is_active'        => array_key_exists('is_active', $arguments) ? (bool) $arguments['is_active'] : true,
                'sort_order'       => $arguments['sort_order']      ?? $maxSort + 1,
            ]);

            return ToolResult::success([
                'id'             => $article->id,
                'uuid'           => $article->uuid,
                'name'           => $article->name,
                'article_number' => $article->article_number,
                'article_group_id' => $article->article_group_id,
                'is_active'      => (bool) $article->is_active,
                'message'        => "Artikel '{$article->name}' angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'write', 'idempotent' => false, 'side_effects' => ['creates'],
        ];
    }
}
