<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Article;
use Platform\Events\Models\ArticleGroup;

class UpdateArticleTool implements ToolContract, ToolMetadataContract
{
    protected const STRING_FIELDS = [
        'article_number', 'external_code', 'name', 'description',
        'offer_text', 'invoice_text', 'gebinde', 'mwst', 'erloeskonto',
        'lagerort', 'procurement_type',
    ];
    protected const NUMERIC_FIELDS = ['ek', 'vk'];
    protected const INT_FIELDS     = ['min_bestand', 'current_bestand', 'sort_order'];

    public function getName(): string
    {
        return 'events.articles.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/articles/{id} - Aktualisiert einen Artikel. '
            . 'Identifikation: article_id ODER uuid ODER article_number. '
            . 'Felder (alle optional): article_group_id, article_number, external_code, name, description, '
            . 'offer_text, invoice_text, gebinde, ek, vk, mwst ("0%"|"7%"|"19%"), erloeskonto, '
            . 'lagerort, min_bestand, current_bestand, procurement_type ("stock"|"supplier"|"kitchen"), '
            . 'is_active (bool), sort_order.';
    }

    public function getSchema(): array
    {
        $props = [
            'article_id'       => ['type' => 'integer'],
            'uuid'             => ['type' => 'string'],
            'article_number'   => ['type' => 'string'],
            'article_group_id' => ['type' => 'integer'],
            'is_active'        => ['type' => 'boolean'],
        ];
        foreach (self::STRING_FIELDS as $f)  $props[$f] = ['type' => 'string'];
        foreach (self::NUMERIC_FIELDS as $f) $props[$f] = ['type' => 'number'];
        foreach (self::INT_FIELDS as $f)     $props[$f] = ['type' => 'integer'];
        return ['type' => 'object', 'properties' => $props];
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
            $article = $query->first();
            if (!$article) {
                return ToolResult::error('ARTICLE_NOT_FOUND', 'Artikel nicht gefunden.');
            }
            if (!$context->user->teams()->where('teams.id', $article->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf den Artikel.');
            }

            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) $update[$f] = $arguments[$f];
            }
            foreach (self::NUMERIC_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) $update[$f] = $arguments[$f] !== null ? (float) $arguments[$f] : null;
            }
            foreach (self::INT_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) $update[$f] = $arguments[$f] !== null ? (int) $arguments[$f] : null;
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }
            if (array_key_exists('article_group_id', $arguments)) {
                $group = ArticleGroup::where('team_id', $article->team_id)->find((int) $arguments['article_group_id']);
                if (!$group) {
                    return ToolResult::error('VALIDATION_ERROR', 'article_group_id gehoert nicht zum Team.');
                }
                $update['article_group_id'] = $group->id;
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $known = array_merge(
                ['article_id', 'uuid', 'article_group_id', 'is_active'],
                self::STRING_FIELDS, self::NUMERIC_FIELDS, self::INT_FIELDS
            );
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $article->update($update);

            return ToolResult::success([
                'id'             => $article->id,
                'uuid'           => $article->uuid,
                'name'           => $article->name,
                'article_number' => $article->article_number,
                'is_active'      => (bool) $article->is_active,
                'updated_fields' => array_keys($update),
                'ignored_fields' => $ignored,
                'message'        => 'Artikel aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article', 'update'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
