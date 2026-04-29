<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Article;
use Platform\Events\Models\ArticleGroup;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;

class UpdateArticleTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;

    protected const STRING_FIELDS = [
        'article_number', 'external_code', 'name', 'description',
        'offer_text', 'invoice_text', 'gebinde', 'erloeskonto', 'lagerort',
    ];
    protected const NUMERIC_FIELDS = ['ek', 'vk'];
    protected const INT_FIELDS     = ['min_bestand', 'current_bestand', 'sort_order'];

    /** Aliases (gleicher Mechanismus wie CreateArticleTool). */
    protected const FIELD_ALIASES = [
        'price_net' => 'vk',
        'price'     => 'vk',
        'cost'      => 'ek',
        'tax_rate'  => 'mwst',
        'unit'      => 'gebinde',
        'group_id'  => 'article_group_id',
    ];

    public function getName(): string
    {
        return 'events.articles.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/articles/{id} - Aktualisiert einen Artikel. '
            . 'Identifikation: article_id ODER uuid ODER article_number. '
            . 'Felder (alle optional): article_group_id, article_number (eindeutig pro Team), '
            . 'external_code, name, description, offer_text, invoice_text, '
            . 'gebinde (Alias unit), ek (Alias cost), vk (Alias price_net|price), '
            . 'mwst (Strikt: "0%"|"7%"|"19%"; Alias tax_rate), erloeskonto, '
            . 'lagerort (optional, kann null gesetzt werden), min_bestand, current_bestand, '
            . 'procurement_type ("stock"|"supplier"|"kitchen"; STRIKT), is_active, sort_order.';
    }

    public function getSchema(): array
    {
        $props = [
            'article_id'       => ['type' => 'integer'],
            'uuid'             => ['type' => 'string'],
            'article_group_id' => ['type' => 'integer'],
            'group_id'         => ['type' => 'integer', 'description' => 'Alias fuer article_group_id.'],
            'is_active'        => ['type' => 'boolean'],
            'mwst'             => ['type' => 'string', 'enum' => CreateArticleTool::MWST_OPTIONS],
            'tax_rate'         => ['type' => 'string', 'enum' => CreateArticleTool::MWST_OPTIONS, 'description' => 'Alias fuer mwst.'],
            'procurement_type' => ['type' => 'string', 'enum' => CreateArticleTool::PROCUREMENT_TYPES],
            'unit'             => ['type' => 'string', 'description' => 'Alias fuer gebinde.'],
            'price_net'        => ['type' => 'number', 'description' => 'Alias fuer vk.'],
            'price'            => ['type' => 'number', 'description' => 'Alias fuer vk.'],
            'cost'             => ['type' => 'number', 'description' => 'Alias fuer ek.'],
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
                // Achtung: kann bei Update-Identifikation kollidieren wenn auch ein neuer
                // article_number gesetzt werden soll. Wir nutzen article_number als
                // SELECTOR nur, wenn weder article_id noch uuid vorhanden sind.
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

            // Aliases auf primaere Feldnamen mappen.
            $aliasesApplied = [];
            foreach (self::FIELD_ALIASES as $alias => $primary) {
                if (array_key_exists($alias, $arguments)
                    && (!array_key_exists($primary, $arguments) || $arguments[$primary] === null || $arguments[$primary] === '')
                ) {
                    $arguments[$primary] = $arguments[$alias];
                    $aliasesApplied[] = "{$alias}→{$primary}";
                }
            }

            // ----- Strict Pre-Validation -----
            $errors = [];

            if (array_key_exists('mwst', $arguments)
                && $arguments['mwst'] !== null && $arguments['mwst'] !== ''
                && !in_array($arguments['mwst'], CreateArticleTool::MWST_OPTIONS, true)
            ) {
                $errors[] = $this->validationError('mwst', 'mwst muss einer von: ' . implode(', ', CreateArticleTool::MWST_OPTIONS));
            }
            if (array_key_exists('procurement_type', $arguments)
                && $arguments['procurement_type'] !== null && $arguments['procurement_type'] !== ''
                && !in_array($arguments['procurement_type'], CreateArticleTool::PROCUREMENT_TYPES, true)
            ) {
                $errors[] = $this->validationError('procurement_type', 'procurement_type muss einer von: ' . implode(', ', CreateArticleTool::PROCUREMENT_TYPES));
            }
            if (array_key_exists('article_group_id', $arguments)
                && $arguments['article_group_id'] !== null && $arguments['article_group_id'] !== ''
            ) {
                $group = ArticleGroup::where('team_id', $article->team_id)->find((int) $arguments['article_group_id']);
                if (!$group) {
                    $errors[] = $this->validationError('article_group_id', 'article_group_id gehoert nicht zum Team.');
                }
            }
            if (array_key_exists('article_number', $arguments)
                && $arguments['article_number'] !== null && $arguments['article_number'] !== ''
                && (string) $arguments['article_number'] !== (string) $article->article_number
            ) {
                $exists = Article::withTrashed()
                    ->where('team_id', $article->team_id)
                    ->where('article_number', $arguments['article_number'])
                    ->where('id', '!=', $article->id)
                    ->exists();
                if ($exists) {
                    $errors[] = $this->validationError('article_number', "article_number '{$arguments['article_number']}' ist bereits vergeben (team-eindeutig).");
                }
            }
            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }

            // ----- Update zusammenstellen -----
            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $value = $arguments[$f];
                    // gebinde / lagerort sind nullable: '' → null
                    if (in_array($f, ['gebinde', 'lagerort'], true)) {
                        $value = ($value === null || trim((string) $value) === '') ? null : trim((string) $value);
                    }
                    $update[$f] = $value;
                }
            }
            foreach (self::NUMERIC_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f] !== null ? (float) $arguments[$f] : null;
                }
            }
            foreach (self::INT_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $update[$f] = $arguments[$f] !== null ? (int) $arguments[$f] : null;
                }
            }
            if (array_key_exists('mwst', $arguments)) {
                $update['mwst'] = $arguments['mwst'];
            }
            if (array_key_exists('procurement_type', $arguments)) {
                $update['procurement_type'] = $arguments['procurement_type'];
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }
            if (array_key_exists('article_group_id', $arguments)) {
                $update['article_group_id'] = (int) $arguments['article_group_id'];
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $known = array_merge(
                ['article_id', 'uuid', 'article_group_id', 'is_active', 'mwst', 'procurement_type'],
                self::STRING_FIELDS, self::NUMERIC_FIELDS, self::INT_FIELDS,
                array_keys(self::FIELD_ALIASES),
            );
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $article->update($update);

            return ToolResult::success([
                'id'             => $article->id,
                'uuid'           => $article->uuid,
                'name'           => $article->name,
                'article_number' => $article->article_number,
                'gebinde'        => $article->gebinde,
                'ek'             => (float) $article->ek,
                'vk'             => (float) $article->vk,
                'mwst'           => $article->mwst,
                'procurement_type' => $article->procurement_type,
                'lagerort'       => $article->lagerort,
                'is_active'      => (bool) $article->is_active,
                'updated_fields' => array_keys($update),
                'aliases_applied'=> $aliasesApplied,
                'ignored_fields' => $ignored,
                '_field_hints'   => [
                    'mwst'             => 'Strikt: ' . implode(' | ', CreateArticleTool::MWST_OPTIONS),
                    'procurement_type' => 'Strikt: ' . implode(' | ', CreateArticleTool::PROCUREMENT_TYPES),
                    'gebinde'          => 'Optional. Leer-String wird zu null normalisiert.',
                    'lagerort'         => 'Optional. Leer-String wird zu null normalisiert.',
                    'article_number'   => 'Eindeutig pro Team. Update auf bereits vergebenen Wert wird abgelehnt.',
                ],
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
