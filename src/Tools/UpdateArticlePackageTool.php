<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticlePackage;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;

class UpdateArticlePackageTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;

    protected const STRING_FIELDS = ['name', 'description', 'color'];

    /** Aliases (Naming-Bridge zur restlichen API). */
    protected const FIELD_ALIASES = [
        'article_package_id' => 'package_id',
    ];

    /** Erlaubte Formate: #RGB | #RRGGBB | #RRGGBBAA (case-insensitive). */
    protected const COLOR_REGEX = '/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/';

    public function getName(): string
    {
        return 'events.article-packages.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/article-packages/{id} - Aktualisiert ein Paket. '
            . 'Identifikation: package_id (Alias article_package_id) ODER uuid. '
            . 'Felder: name, description, color (#RGB | #RRGGBB | #RRGGBBAA), is_active, sort_order.';
    }

    public function getSchema(): array
    {
        $props = [
            'package_id'         => ['type' => 'integer'],
            'article_package_id' => ['type' => 'integer', 'description' => 'Alias fuer package_id.'],
            'uuid'               => ['type' => 'string'],
            'is_active'          => ['type' => 'boolean'],
            'sort_order'         => ['type' => 'integer'],
        ];
        foreach (self::STRING_FIELDS as $f) $props[$f] = ['type' => 'string'];
        return ['type' => 'object', 'properties' => $props];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            // Aliases mappen (article_package_id → package_id).
            $aliasesApplied = [];
            foreach (self::FIELD_ALIASES as $alias => $primary) {
                if (array_key_exists($alias, $arguments)
                    && (!array_key_exists($primary, $arguments) || $arguments[$primary] === null || $arguments[$primary] === '')
                ) {
                    $arguments[$primary] = $arguments[$alias];
                    $aliasesApplied[] = "{$alias}→{$primary}";
                }
            }

            $query = ArticlePackage::query();
            if (!empty($arguments['package_id'])) {
                $query->where('id', (int) $arguments['package_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'package_id (oder Alias article_package_id) oder uuid ist erforderlich.');
            }
            $package = $query->first();
            if (!$package) {
                return ToolResult::error('PACKAGE_NOT_FOUND', 'Paket nicht gefunden.');
            }
            if (!$context->user->teams()->where('teams.id', $package->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            // Strict-Validation (gebuendelt).
            $errors = [];
            if (array_key_exists('color', $arguments)
                && $arguments['color'] !== null && $arguments['color'] !== ''
                && !preg_match(self::COLOR_REGEX, (string) $arguments['color'])
            ) {
                $errors[] = $this->validationError(
                    'color',
                    'color muss Hex-Format haben: #RGB, #RRGGBB oder #RRGGBBAA (z.B. "#8b5cf6").'
                );
            }

            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $val = $arguments[$f];
                    // color darf nicht NULL/"" gesetzt werden (Spalte ist NOT NULL mit DB-Default).
                    if ($f === 'color' && ($val === null || $val === '')) {
                        $errors[] = $this->validationError('color', 'color darf nicht leer sein.');
                        continue;
                    }
                    $update[$f] = $val === null ? null : (string) $val;
                }
            }
            if (array_key_exists('is_active', $arguments)) $update['is_active'] = (bool) $arguments['is_active'];
            if (array_key_exists('sort_order', $arguments)) $update['sort_order'] = (int) $arguments['sort_order'];

            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }
            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $known = array_merge(
                ['package_id', 'uuid', 'is_active', 'sort_order'],
                self::STRING_FIELDS,
                array_keys(self::FIELD_ALIASES),
            );
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $package->update($update);

            return ToolResult::success([
                'id'               => $package->id,
                'uuid'             => $package->uuid,
                'name'             => $package->name,
                'description'      => $package->description,
                'color'            => $package->color,
                'is_active'        => (bool) $package->is_active,
                'sort_order'       => (int) $package->sort_order,
                'updated_fields'   => array_keys($update),
                'aliases_applied'  => $aliasesApplied,
                'ignored_fields'   => $ignored,
                '_field_hints'     => [
                    'package_id' => 'Alias: article_package_id.',
                    'color'      => 'Hex-Format: #RGB | #RRGGBB | #RRGGBBAA. Nicht leer setzen.',
                ],
                'message'          => 'Paket aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-package', 'update'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
