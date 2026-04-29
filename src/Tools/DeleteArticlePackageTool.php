<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticlePackage;

class DeleteArticlePackageTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.article-packages.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/article-packages/{id} - Soft-Delete eines Pakets inkl. seiner PackageItems. '
            . 'Identifikation: package_id ODER uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'package_id' => ['type' => 'integer'],
                'uuid'       => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            $query = ArticlePackage::query();
            if (!empty($arguments['package_id'])) {
                $query->where('id', (int) $arguments['package_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'package_id oder uuid ist erforderlich.');
            }
            $package = $query->first();
            if (!$package) {
                return ToolResult::error('PACKAGE_NOT_FOUND', 'Paket nicht gefunden.');
            }
            if (!$context->user->teams()->where('teams.id', $package->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }
            $id = $package->id;
            $name = $package->name;
            // Items mit-soft-deleten
            $package->items()->delete();
            $package->delete();
            return ToolResult::success([
                'id'      => $id,
                'name'    => $name,
                'message' => "Paket '{$name}' geloescht (soft, inkl. Items).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-package', 'delete'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'destructive', 'idempotent' => false, 'side_effects' => ['deletes'],
        ];
    }
}
