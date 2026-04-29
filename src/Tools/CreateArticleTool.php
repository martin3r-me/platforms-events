<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Article;
use Platform\Events\Models\ArticleGroup;
use Platform\Events\Services\ArticleNumberGenerator;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;

class CreateArticleTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;

    /** Hardcoded Enums (Strict). */
    public const MWST_OPTIONS             = ['0%', '7%', '19%'];
    public const PROCUREMENT_TYPES        = ['stock', 'supplier', 'kitchen'];

    /** Aliase auf primaere Feldnamen, damit verschiedene API-Konventionen
     *  toleriert werden (price_net → vk, tax_rate → mwst, unit → gebinde). */
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
        return 'events.articles.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/articles - Legt einen Artikel-Stammdatensatz an. '
            . 'Pflicht: name, article_group_id (FK events_article_groups.id). '
            . '[Stammdaten] article_number (string, optional – wird auto-generiert "ART-{teamId}-{seq}" wenn leer). '
            . '[Texte] description, offer_text, invoice_text, external_code (z.B. WaWi-Nummer). '
            . '[Preise] ek (decimal, EK-Preis), vk (decimal, VK-Preis; Alias: price_net|price), '
            . 'mwst ("0%"|"7%"|"19%"; Alias: tax_rate; default "7%"), erloeskonto (string, optional, ueberschreibt Group-Default). '
            . '[Einheit] gebinde (string, z.B. "1 Port."; Alias: unit; optional). '
            . '[Lager] lagerort (string, optional – Mietartikel/Hallen brauchen keinen), '
            . 'min_bestand (int, default 0), current_bestand (int, default 0). '
            . '[Beschaffung] procurement_type ("stock" | "supplier" | "kitchen"; default "stock"). '
            . '[System] is_active (bool, default true), sort_order (int).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'          => ['type' => 'integer', 'description' => '[System] Default: aktuelles Team.'],
                'name'             => ['type' => 'string',  'description' => '[Stammdaten] (PFLICHT)'],
                'article_group_id' => ['type' => 'integer', 'description' => '[Stammdaten] (PFLICHT) FK events_article_groups.id. Alias: group_id.'],
                'group_id'         => ['type' => 'integer', 'description' => 'Alias fuer article_group_id.'],
                'article_number'   => ['type' => 'string',  'description' => '[Stammdaten] Optional – wird auto-generiert wenn leer.'],
                'external_code'    => ['type' => 'string',  'description' => '[Stammdaten] z.B. WaWi-Nummer.'],
                'description'      => ['type' => 'string',  'description' => '[Texte]'],
                'offer_text'       => ['type' => 'string',  'description' => '[Texte] erscheint im Angebot.'],
                'invoice_text'     => ['type' => 'string',  'description' => '[Texte] erscheint auf der Rechnung.'],
                'gebinde'          => ['type' => 'string',  'description' => '[Einheit] z.B. "1 Port.". Aliases: unit. Optional.'],
                'unit'             => ['type' => 'string',  'description' => 'Alias fuer gebinde.'],
                'ek'               => ['type' => 'number',  'description' => '[Preise] Einkaufs-Netto. Aliases: cost.'],
                'cost'             => ['type' => 'number',  'description' => 'Alias fuer ek.'],
                'vk'               => ['type' => 'number',  'description' => '[Preise] Verkaufs-Netto. Aliases: price_net | price.'],
                'price_net'        => ['type' => 'number',  'description' => 'Alias fuer vk.'],
                'price'            => ['type' => 'number',  'description' => 'Alias fuer vk.'],
                'mwst'             => ['type' => 'string',  'enum' => self::MWST_OPTIONS, 'description' => '[Preise] STRIKT. Aliases: tax_rate. Default "7%".'],
                'tax_rate'         => ['type' => 'string',  'enum' => self::MWST_OPTIONS, 'description' => 'Alias fuer mwst.'],
                'erloeskonto'      => ['type' => 'string',  'description' => '[Preise] Optional – ueberschreibt das Default der Gruppe.'],
                'lagerort'         => ['type' => 'string',  'description' => '[Lager] Optional. Bei Mietartikeln/Hallen sinnvoll leer.'],
                'min_bestand'      => ['type' => 'integer'],
                'current_bestand'  => ['type' => 'integer'],
                'procurement_type' => ['type' => 'string',  'enum' => self::PROCUREMENT_TYPES, 'description' => '[Beschaffung] STRIKT. "stock" = Lager (Packliste), "supplier" = Dienstleister, "kitchen" = Kueche.'],
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

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                $errors[] = $this->validationError('name', 'name ist erforderlich (nicht leer).');
            }

            $groupId = $arguments['article_group_id'] ?? null;
            $group = null;
            if (empty($groupId)) {
                $errors[] = $this->validationError('article_group_id', 'article_group_id ist erforderlich (FK events_article_groups.id).');
            } else {
                $group = ArticleGroup::where('team_id', $teamId)->find((int) $groupId);
                if (!$group) {
                    $errors[] = $this->validationError('article_group_id', "ArticleGroup-ID {$groupId} existiert nicht oder gehoert nicht zum Team.");
                }
            }

            $mwst = $arguments['mwst'] ?? '7%';
            if (!in_array($mwst, self::MWST_OPTIONS, true)) {
                $errors[] = $this->validationError('mwst', 'mwst muss einer von: ' . implode(', ', self::MWST_OPTIONS));
            }

            $procurementType = $arguments['procurement_type'] ?? 'stock';
            if (!in_array($procurementType, self::PROCUREMENT_TYPES, true)) {
                $errors[] = $this->validationError('procurement_type', 'procurement_type muss einer von: ' . implode(', ', self::PROCUREMENT_TYPES));
            }

            // article_number: optional via Auto-Generator. Wenn explizit gesetzt: Unique pro Team pruefen.
            $articleNumber = trim((string) ($arguments['article_number'] ?? ''));
            $articleNumberSource = 'request';
            if ($articleNumber === '') {
                $articleNumber = ArticleNumberGenerator::next($teamId);
                $articleNumberSource = 'auto_generated';
            } else {
                $exists = Article::withTrashed()
                    ->where('team_id', $teamId)
                    ->where('article_number', $articleNumber)
                    ->exists();
                if ($exists) {
                    $errors[] = $this->validationError('article_number', "article_number '{$articleNumber}' existiert bereits in diesem Team. Leer lassen fuer Auto-Generierung.");
                }
            }

            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }

            // ----- Bekannte Felder + ignored_fields -----
            $known = array_merge([
                'team_id', 'name', 'article_group_id', 'article_number', 'external_code',
                'description', 'offer_text', 'invoice_text',
                'gebinde', 'ek', 'vk', 'mwst', 'erloeskonto',
                'lagerort', 'min_bestand', 'current_bestand',
                'procurement_type', 'is_active', 'sort_order',
            ], array_keys(self::FIELD_ALIASES));
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            // ----- Insert -----
            $maxSort = (int) Article::where('team_id', $teamId)
                ->where('article_group_id', $group->id)
                ->max('sort_order');

            $article = Article::create([
                'team_id'          => $teamId,
                'user_id'          => $context->user->id,
                'article_group_id' => $group->id,
                'article_number'   => $articleNumber,
                'external_code'    => $arguments['external_code']   ?? null,
                'name'             => $name,
                'description'      => $arguments['description']     ?? null,
                'offer_text'       => $arguments['offer_text']      ?? null,
                'invoice_text'     => $arguments['invoice_text']    ?? null,
                'gebinde'          => isset($arguments['gebinde']) && trim((string) $arguments['gebinde']) !== ''
                                        ? trim((string) $arguments['gebinde']) : null,
                'ek'               => isset($arguments['ek']) ? (float) $arguments['ek'] : 0,
                'vk'               => isset($arguments['vk']) ? (float) $arguments['vk'] : 0,
                'mwst'             => $mwst,
                'erloeskonto'      => $arguments['erloeskonto']     ?? null,
                'lagerort'         => isset($arguments['lagerort']) && trim((string) $arguments['lagerort']) !== ''
                                        ? trim((string) $arguments['lagerort']) : null,
                'min_bestand'      => isset($arguments['min_bestand']) ? (int) $arguments['min_bestand'] : 0,
                'current_bestand'  => isset($arguments['current_bestand']) ? (int) $arguments['current_bestand'] : 0,
                'procurement_type' => $procurementType,
                'is_active'        => array_key_exists('is_active', $arguments) ? (bool) $arguments['is_active'] : true,
                'sort_order'       => $arguments['sort_order'] ?? $maxSort + 1,
            ]);

            // ----- Empty recommended (kontextabhaengig) -----
            $emptyRecommended = [];
            if (empty($article->offer_text))   $emptyRecommended['offer_text']   = 'Text, der im Angebot erscheint. Kann den name uebersteuern.';
            if (empty($article->invoice_text)) $emptyRecommended['invoice_text'] = 'Text, der auf der Rechnung erscheint. Wenn leer, wird name verwendet.';
            if ((float) $article->vk <= 0)     $emptyRecommended['vk']           = 'VK-Preis ist 0 – Position rechnet sonst mit 0 €.';
            if (empty($article->gebinde))      $emptyRecommended['gebinde']      = 'Mengeneinheit (z.B. "1 Port."). Optional bei Pauschalen / Mietartikeln.';

            return ToolResult::success([
                'id'                       => $article->id,
                'uuid'                     => $article->uuid,
                'name'                     => $article->name,
                'article_number'           => $article->article_number,
                'article_number_source'    => $articleNumberSource, // request | auto_generated
                'article_group_id'         => $article->article_group_id,
                'gebinde'                  => $article->gebinde,
                'ek'                       => (float) $article->ek,
                'vk'                       => (float) $article->vk,
                'mwst'                     => $article->mwst,
                'procurement_type'         => $article->procurement_type,
                'lagerort'                 => $article->lagerort,
                'is_active'                => (bool) $article->is_active,
                'aliases_applied'          => $aliasesApplied,
                'ignored_fields'           => $ignored,
                'empty_recommended_fields' => $emptyRecommended,
                'empty_recommended_field_options' => [
                    'mwst'             => ['values' => self::MWST_OPTIONS,             'strict' => true,  'note' => 'Hardcoded Enum.'],
                    'procurement_type' => ['values' => self::PROCUREMENT_TYPES,        'strict' => true,  'note' => 'Hardcoded Enum: stock | supplier | kitchen.'],
                ],
                '_field_hints'             => [
                    'article_number'   => 'Eindeutig pro Team. Auto-Generation: ART-{teamId}-{seq}, wenn nicht gesetzt.',
                    'gebinde'          => 'Freitext-Mengeneinheit. Bei Pauschalen / Hallenmiete in der Regel leer.',
                    'lagerort'         => 'Optional. Wird in der Packliste angezeigt. Mietartikel/Hallen brauchen keinen.',
                    'erloeskonto'      => 'Wenn leer, wird das Default der ArticleGroup verwendet (erloeskonto_7 oder erloeskonto_19 je nach mwst).',
                    'procurement_type' => 'stock = Eigenes Lager (kommt in Packliste). supplier = Externe Bestellung. kitchen = Eigene Herstellung (Speisen).',
                ],
                'message'                  => "Artikel '{$article->name}' angelegt (#{$article->article_number}).",
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
