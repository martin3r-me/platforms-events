<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Article;

class DeleteArticleTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.articles.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/articles/{id} - Soft-Delete eines Artikels. '
            . 'Identifikation: article_id ODER uuid ODER article_number. '
            . 'Hinweis: Artikel werden referenziert (z.B. ArticlePackageItem) – Soft-Delete bleibt sichtbar im Audit. '
            . 'Alternative: is_active=false setzen via PATCH (nicht-destruktiv).';
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
            $article = $query->first();
            if (!$article) {
                return ToolResult::error('ARTICLE_NOT_FOUND', 'Artikel nicht gefunden.');
            }
            if (!$context->user->teams()->where('teams.id', $article->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf den Artikel.');
            }
            $id = $article->id;
            $name = $article->name;
            $article->delete();
            return ToolResult::success([
                'id'      => $id,
                'name'    => $name,
                'message' => "Artikel '{$name}' geloescht (soft).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article', 'delete'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'destructive', 'idempotent' => false, 'side_effects' => ['deletes'],
        ];
    }
}
