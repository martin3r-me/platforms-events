<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticlePackageItem;

class DeleteArticlePackageItemTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.article-package-items.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/article-package-items/{id} - Soft-Delete eines Package-Items. '
            . 'Identifikation: item_id ODER uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'item_id' => ['type' => 'integer'],
                'uuid'    => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            $query = ArticlePackageItem::query();
            if (!empty($arguments['item_id'])) {
                $query->where('id', (int) $arguments['item_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'item_id oder uuid ist erforderlich.');
            }
            $item = $query->first();
            if (!$item) {
                return ToolResult::error('ITEM_NOT_FOUND', 'Item nicht gefunden.');
            }
            if (!$context->user->teams()->where('teams.id', $item->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }
            $id = $item->id;
            $name = $item->name;
            $item->delete();
            return ToolResult::success([
                'id'      => $id,
                'name'    => $name,
                'message' => "Item '{$name}' geloescht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-package-item', 'delete'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'destructive', 'idempotent' => false, 'side_effects' => ['deletes'],
        ];
    }
}
