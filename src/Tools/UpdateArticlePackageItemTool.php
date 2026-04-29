<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticlePackageItem;

class UpdateArticlePackageItemTool implements ToolContract, ToolMetadataContract
{
    protected const STRING_FIELDS = ['name', 'gruppe', 'gebinde'];
    protected const NUMERIC_FIELDS = ['vk', 'gesamt'];

    public function getName(): string
    {
        return 'events.article-package-items.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/article-package-items/{id} - Aktualisiert ein Package-Item. '
            . 'Identifikation: item_id ODER uuid. Felder: name, gruppe, quantity, gebinde, vk, gesamt, sort_order, article_id.';
    }

    public function getSchema(): array
    {
        $props = [
            'item_id'    => ['type' => 'integer'],
            'uuid'       => ['type' => 'string'],
            'article_id' => ['type' => 'integer'],
            'quantity'   => ['type' => 'integer'],
            'sort_order' => ['type' => 'integer'],
        ];
        foreach (self::STRING_FIELDS as $f) $props[$f] = ['type' => 'string'];
        foreach (self::NUMERIC_FIELDS as $f) $props[$f] = ['type' => 'number'];
        return ['type' => 'object', 'properties' => $props];
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

            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) $update[$f] = (string) $arguments[$f];
            }
            foreach (self::NUMERIC_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) $update[$f] = $arguments[$f] !== null ? (float) $arguments[$f] : null;
            }
            if (array_key_exists('quantity', $arguments)) {
                $update['quantity'] = (int) $arguments['quantity'];
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int) $arguments['sort_order'];
            }
            if (array_key_exists('article_id', $arguments)) {
                $update['article_id'] = $arguments['article_id'] ? (int) $arguments['article_id'] : null;
            }
            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }
            $item->update($update);

            return ToolResult::success([
                'id'             => $item->id,
                'uuid'           => $item->uuid,
                'package_id'     => $item->package_id,
                'name'           => $item->name,
                'quantity'       => (int) $item->quantity,
                'vk'             => (float) $item->vk,
                'gesamt'         => (float) $item->gesamt,
                'updated_fields' => array_keys($update),
                'message'        => 'Item aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-package-item', 'update'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
