<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\ArticleGroup;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;

class CreateArticleGroupTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;

    /** Erlaubte Formate: #RGB | #RRGGBB | #RRGGBBAA (case-insensitive). */
    protected const COLOR_REGEX = '/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/';

    public function getName(): string
    {
        return 'events.article-groups.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/article-groups - Legt eine Artikelgruppe an. Pflicht: name. '
            . 'Optional: parent_id (FK fuer Baumstruktur), color (#RGB | #RRGGBB | #RRGGBBAA; '
            . 'wenn nicht gesetzt: erbt von parent_id, sonst DB-Default), '
            . 'erloeskonto_7 / erloeskonto_19 (Default-Konten fuer 7%/19% MwSt – '
            . 'vererben sich auf Artikel ohne eigenes erloeskonto), '
            . 'is_active (bool, default true), sort_order.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'        => ['type' => 'integer'],
                'name'           => ['type' => 'string'],
                'parent_id'      => ['type' => 'integer'],
                'color'          => ['type' => 'string', 'description' => 'Hex-Farbe, z.B. #6366f1. Optional.'],
                'erloeskonto_7'  => ['type' => 'string'],
                'erloeskonto_19' => ['type' => 'string'],
                'is_active'      => ['type' => 'boolean'],
                'sort_order'     => ['type' => 'integer'],
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
                    'color muss Hex-Format haben: #RGB, #RRGGBB oder #RRGGBBAA (z.B. "#6366f1").'
                );
            }

            $parent = null;
            if (!empty($arguments['parent_id'])) {
                $parent = ArticleGroup::where('team_id', $teamId)->find((int) $arguments['parent_id']);
                if (!$parent) {
                    $errors[] = $this->validationError('parent_id', 'parent_id gehoert nicht zum Team.');
                }
            }
            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }

            $maxSort = (int) ArticleGroup::where('team_id', $teamId)
                ->when($parent, fn ($q) => $q->where('parent_id', $parent->id), fn ($q) => $q->whereNull('parent_id'))
                ->max('sort_order');

            // Color-Resolution: explizit > parent.color > DB-Default (Spalte einfach weglassen).
            $colorSource = 'db_default';
            $payload = [
                'team_id'    => $teamId,
                'user_id'    => $context->user->id,
                'parent_id'  => $parent?->id,
                'name'       => $arguments['name'],
                'is_active'  => array_key_exists('is_active', $arguments) ? (bool) $arguments['is_active'] : true,
                'sort_order' => $arguments['sort_order'] ?? $maxSort + 1,
            ];
            if ($hasColor) {
                $payload['color'] = (string) $arguments['color'];
                $colorSource = 'explicit';
            } elseif ($parent && $parent->color) {
                $payload['color'] = $parent->color;
                $colorSource = 'inherited_from_parent';
            }
            // erloeskonto_7/19 nur setzen, wenn explizit uebergeben (NULL ist erlaubt).
            foreach (['erloeskonto_7', 'erloeskonto_19'] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $payload[$f] = $arguments[$f] !== null && $arguments[$f] !== '' ? (string) $arguments[$f] : null;
                }
            }

            $group = ArticleGroup::create($payload);

            $known = [
                'team_id', 'name', 'parent_id', 'color',
                'erloeskonto_7', 'erloeskonto_19', 'is_active', 'sort_order',
            ];
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $emptyRecommended = [];
            if (!$group->erloeskonto_7) {
                $emptyRecommended['erloeskonto_7'] = 'Default-Erloeskonto fuer 7% MwSt (vererbt sich auf Artikel).';
            }
            if (!$group->erloeskonto_19) {
                $emptyRecommended['erloeskonto_19'] = 'Default-Erloeskonto fuer 19% MwSt (vererbt sich auf Artikel).';
            }

            return ToolResult::success([
                'id'             => $group->id,
                'uuid'           => $group->uuid,
                'name'           => $group->name,
                'parent_id'      => $group->parent_id,
                'color'          => $group->color,
                'color_source'   => $colorSource,
                'is_active'      => (bool) $group->is_active,
                'sort_order'     => (int) $group->sort_order,
                'erloeskonto_7'  => $group->erloeskonto_7,
                'erloeskonto_19' => $group->erloeskonto_19,
                'aliases_applied' => [],
                'ignored_fields'  => $ignored,
                'empty_recommended_fields' => $emptyRecommended,
                '_field_hints'    => [
                    'color' => 'Hex-Format. Wenn weggelassen: erbt von parent_id.color, sonst DB-Default.',
                    'erloeskonto_7'  => 'Vererbt sich auf alle Artikel der Gruppe ohne eigenes erloeskonto_7.',
                    'erloeskonto_19' => 'Vererbt sich auf alle Artikel der Gruppe ohne eigenes erloeskonto_19.',
                ],
                'message'         => "Gruppe '{$group->name}' angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'article-group', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'write', 'idempotent' => false, 'side_effects' => ['creates'],
        ];
    }
}
