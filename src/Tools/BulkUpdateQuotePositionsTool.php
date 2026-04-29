<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\RecalculatesQuoteItem;

/**
 * Massen-Update mehrerer Angebots-Positionen innerhalb eines QuoteItems.
 * Filter + Setzwerte werden in einem Call uebergeben – Recalc des
 * QuoteItems erfolgt automatisch.
 */
class BulkUpdateQuotePositionsTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use RecalculatesQuoteItem;

    protected const SETTABLE_STRING_FIELDS = [
        'gruppe', 'mwst', 'gebinde', 'bemerkung', 'inhalt', 'beverage_mode', 'procurement_type',
    ];
    protected const SETTABLE_NUMERIC_FIELDS = ['ek', 'preis', 'gesamt'];

    /** Aliases (price_net→preis usw.). */
    protected const FIELD_ALIASES = [
        'price_net' => 'preis',
        'price'     => 'preis',
        'vk'        => 'preis',
        'tax_rate'  => 'mwst',
        'unit'      => 'gebinde',
    ];

    public function getName(): string
    {
        return 'events.quote-positions.bulk.PATCH';
    }

    public function getDescription(): string
    {
        return 'POST /events/quote-items/{item}/positions/bulk - Massen-Update von Angebots-Positionen innerhalb eines QuoteItems. '
            . 'Pflicht: quote_item_id|quote_item_uuid + mind. ein Filter ODER confirm_item_wide=true. '
            . 'Filter: position_ids[] (Whitelist), gruppe (exakt), gruppe_contains (substring, case-insensitive), '
            . 'name_contains (substring im name-Feld). '
            . 'Setzwerte unter "set" (mind. einer): gruppe, mwst, gebinde, bemerkung, inhalt, '
            . 'beverage_mode, procurement_type, ek, preis (Aliases: price_net|price|vk), gesamt, sort_order. '
            . 'Aliases werden in set akzeptiert (price_net→preis usw.). '
            . 'Recalc des QuoteItems am Ende automatisch.';
    }

    public function getSchema(): array
    {
        $setProps = [];
        foreach (self::SETTABLE_STRING_FIELDS as $f)  $setProps[$f] = ['type' => 'string'];
        foreach (self::SETTABLE_NUMERIC_FIELDS as $f) $setProps[$f] = ['type' => 'number'];
        $setProps['sort_order'] = ['type' => 'integer'];
        // Aliases im set akzeptieren
        foreach (self::FIELD_ALIASES as $alias => $primary) {
            $type = in_array($primary, self::SETTABLE_NUMERIC_FIELDS, true) ? 'number' : 'string';
            $setProps[$alias] = ['type' => $type, 'description' => "Alias fuer {$primary}."];
        }

        return [
            'type' => 'object',
            'properties' => [
                'quote_item_id'      => ['type' => 'integer'],
                'quote_item_uuid'    => ['type' => 'string'],
                // Filter
                'position_ids'       => ['type' => 'array', 'items' => ['type' => 'integer']],
                'gruppe'             => ['type' => 'string', 'description' => 'Filter: exakte Gruppe.'],
                'gruppe_contains'    => ['type' => 'string', 'description' => 'Filter: Substring in gruppe (case-insensitive).'],
                'name_contains'      => ['type' => 'string', 'description' => 'Filter: Substring in name (case-insensitive).'],
                'confirm_item_wide'  => ['type' => 'boolean', 'description' => 'true = ALLE Positionen des QuoteItems aendern, wenn kein Filter gesetzt ist.'],
                'set' => [
                    'type'        => 'object',
                    'description' => 'Werte, die in alle gefilterten Positionen geschrieben werden.',
                    'properties'  => $setProps,
                ],
            ],
            'required' => ['set'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            // QuoteItem aufloesen
            $quoteItem = null;
            if (!empty($arguments['quote_item_id'])) {
                $quoteItem = QuoteItem::find((int) $arguments['quote_item_id']);
            } elseif (!empty($arguments['quote_item_uuid'])) {
                $quoteItem = QuoteItem::where('uuid', $arguments['quote_item_uuid'])->first();
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'quote_item_id oder quote_item_uuid ist erforderlich.');
            }
            if (!$quoteItem) {
                return ToolResult::error('QUOTE_ITEM_NOT_FOUND', 'Vorgang nicht gefunden.');
            }
            $event = $quoteItem->eventDay?->event;
            if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf den Vorgang.');
            }

            // Set-Werte einsammeln + Aliases mappen.
            $set = is_array($arguments['set'] ?? null) ? $arguments['set'] : [];
            $aliasesApplied = [];
            foreach (self::FIELD_ALIASES as $alias => $primary) {
                if (array_key_exists($alias, $set)
                    && (!array_key_exists($primary, $set) || $set[$primary] === null || $set[$primary] === '')
                ) {
                    $set[$primary] = $set[$alias];
                    $aliasesApplied[] = "{$alias}→{$primary}";
                }
            }

            // Validation des set-Blocks
            $errors = [];
            if (array_key_exists('mwst', $set)
                && $set['mwst'] !== null && $set['mwst'] !== ''
                && !in_array($set['mwst'], ['0%', '7%', '19%'], true)
            ) {
                $errors[] = $this->validationError('set.mwst', 'mwst muss einer von: "0%" | "7%" | "19%".');
            }
            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }

            $update = [];
            foreach (self::SETTABLE_STRING_FIELDS as $f) {
                if (array_key_exists($f, $set)) {
                    $value = $set[$f];
                    $update[$f] = ($value === null || $value === '') && in_array($f, ['beverage_mode', 'procurement_type'], true)
                        ? null
                        : ($value === null ? null : (string) $value);
                }
            }
            foreach (self::SETTABLE_NUMERIC_FIELDS as $f) {
                if (array_key_exists($f, $set)) {
                    $update[$f] = $set[$f] !== null ? (float) $set[$f] : null;
                }
            }
            if (array_key_exists('sort_order', $set)) {
                $update['sort_order'] = (int) $set['sort_order'];
            }
            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'set: mindestens ein Setzwert ist erforderlich.');
            }

            // Filter aufbauen
            $query = QuotePosition::where('quote_item_id', $quoteItem->id);
            $hasFilter = false;

            $idList = $arguments['position_ids'] ?? [];
            if (is_array($idList) && !empty($idList)) {
                $ids = array_values(array_unique(array_filter(array_map('intval', $idList))));
                $query->whereIn('id', $ids);
                $hasFilter = true;
            }
            if (!empty($arguments['gruppe'])) {
                $query->where('gruppe', $arguments['gruppe']);
                $hasFilter = true;
            }
            if (!empty($arguments['gruppe_contains'])) {
                $query->where('gruppe', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $arguments['gruppe_contains']) . '%');
                $hasFilter = true;
            }
            if (!empty($arguments['name_contains'])) {
                $query->where('name', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $arguments['name_contains']) . '%');
                $hasFilter = true;
            }
            if (!$hasFilter && empty($arguments['confirm_item_wide'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein Filter angegeben. Setze position_ids[]/gruppe/gruppe_contains/name_contains ODER confirm_item_wide=true.');
            }

            $positions = $query->get();
            if ($positions->isEmpty()) {
                return ToolResult::success([
                    'quote_item_id' => $quoteItem->id,
                    'count'         => 0,
                    'affected_ids'  => [],
                    'set_fields'    => array_keys($update),
                    'message'       => 'Keine Positionen entsprechen dem Filter.',
                ]);
            }

            $known = [
                'quote_item_id', 'quote_item_uuid',
                'position_ids', 'gruppe', 'gruppe_contains', 'name_contains',
                'confirm_item_wide', 'set',
            ];
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $affected = [];
            foreach ($positions as $pos) {
                // Auto-Recompute gesamt wenn anz/preis nicht direkt im set, aber ek/preis geaendert.
                $rowUpdate = $update;
                if (!array_key_exists('gesamt', $rowUpdate) && array_key_exists('preis', $rowUpdate)) {
                    $rowUpdate['gesamt'] = (float) $pos->anz * (float) $rowUpdate['preis'];
                }
                $pos->update($rowUpdate);
                $affected[] = $pos->id;
            }

            // Recalc des QuoteItems am Ende.
            $this->recalcQuoteItem($quoteItem);

            return ToolResult::success([
                'quote_item_id'   => $quoteItem->id,
                'count'           => count($affected),
                'affected_ids'    => $affected,
                'set_fields'      => array_keys($update),
                'aliases_applied' => $aliasesApplied,
                'ignored_fields'  => $ignored,
                'message'         => count($affected) . ' Position(en) aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Update: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'position', 'update', 'bulk'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
