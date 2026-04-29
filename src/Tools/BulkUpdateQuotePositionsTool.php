<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Quote;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\RecalculatesQuoteItem;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Massen-Update mehrerer Angebots-Positionen innerhalb eines QuoteItems.
 * Filter + Setzwerte werden in einem Call uebergeben – Recalc des
 * QuoteItems erfolgt automatisch.
 */
class BulkUpdateQuotePositionsTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use RecalculatesQuoteItem;
    use ResolvesEvent;

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
        return 'POST /events/quote-positions/bulk - Massen-Update von Angebots-Positionen. '
            . 'SCOPE (genau einer der drei): '
            . '(1) quote_item_id|quote_item_uuid – nur Positionen dieses Vorgangs. '
            . '(2) quote_id|quote_uuid|quote_token – alle Positionen aller Items dieses Angebots (cross-item). '
            . '(3) event_id|event_uuid|event_number – alle Positionen aller Items aller Tage des Events. '
            . 'INNERHALB des Scopes mind. ein Filter ODER confirm_scope_wide=true: '
            . 'position_ids[], gruppe (exakt), gruppe_contains, name_contains. '
            . 'Setzwerte unter "set" (mind. einer): gruppe, mwst, gebinde, bemerkung, inhalt, '
            . 'beverage_mode, procurement_type, ek, preis (Aliases: price_net|price|vk), gesamt, sort_order. '
            . 'Aliases werden in set akzeptiert (price_net→preis usw.). '
            . 'Recalc aller betroffenen QuoteItems am Ende automatisch.';
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
            'properties' => array_merge($this->eventSelectorSchema(), [
                // Scope-Optionen (genau einer)
                'quote_item_id'      => ['type' => 'integer', 'description' => 'Scope: nur Positionen dieses Vorgangs.'],
                'quote_item_uuid'    => ['type' => 'string'],
                'quote_id'           => ['type' => 'integer', 'description' => 'Scope: alle Positionen aller Items dieses Angebots.'],
                'quote_uuid'         => ['type' => 'string'],
                'quote_token'        => ['type' => 'string', 'description' => 'Public-Token des Angebots (48 Zeichen).'],
                // Filter innerhalb des Scopes
                'position_ids'       => ['type' => 'array', 'items' => ['type' => 'integer']],
                'gruppe'             => ['type' => 'string', 'description' => 'Filter: exakte Gruppe.'],
                'gruppe_contains'    => ['type' => 'string', 'description' => 'Filter: Substring in gruppe (case-insensitive).'],
                'name_contains'      => ['type' => 'string', 'description' => 'Filter: Substring in name (case-insensitive).'],
                'confirm_scope_wide' => ['type' => 'boolean', 'description' => 'true = ALLE Positionen im Scope aendern, wenn kein zusaetzlicher Filter gesetzt ist.'],
                'confirm_item_wide'  => ['type' => 'boolean', 'description' => 'Alias fuer confirm_scope_wide (Backwards-Kompat).'],
                'set' => [
                    'type'        => 'object',
                    'description' => 'Werte, die in alle gefilterten Positionen geschrieben werden.',
                    'properties'  => $setProps,
                ],
            ]),
            'required' => ['set'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            // ----- Scope aufloesen (genau einer der drei) -----
            $scope = null; // 'item' | 'quote' | 'event'
            $scopeRef = null;
            $eventForAccess = null;

            if (!empty($arguments['quote_item_id']) || !empty($arguments['quote_item_uuid'])) {
                $scope = 'item';
                $scopeRef = !empty($arguments['quote_item_id'])
                    ? QuoteItem::find((int) $arguments['quote_item_id'])
                    : QuoteItem::where('uuid', $arguments['quote_item_uuid'])->first();
                if (!$scopeRef) {
                    return ToolResult::error('QUOTE_ITEM_NOT_FOUND', 'Vorgang nicht gefunden.');
                }
                $eventForAccess = $scopeRef->eventDay?->event;
            } elseif (!empty($arguments['quote_id']) || !empty($arguments['quote_uuid']) || !empty($arguments['quote_token'])) {
                $scope = 'quote';
                $q = Quote::query();
                if (!empty($arguments['quote_id']))    $q->where('id', (int) $arguments['quote_id']);
                if (!empty($arguments['quote_uuid']))  $q->where('uuid', $arguments['quote_uuid']);
                if (!empty($arguments['quote_token'])) $q->where('token', $arguments['quote_token']);
                $scopeRef = $q->first();
                if (!$scopeRef) {
                    return ToolResult::error('QUOTE_NOT_FOUND', 'Angebot nicht gefunden.');
                }
                $eventForAccess = $scopeRef->event;
            } elseif (!empty($arguments['event_id']) || !empty($arguments['event_uuid']) || !empty($arguments['event_number'])) {
                $resolved = $this->resolveEvent($arguments, $context);
                if ($resolved instanceof ToolResult) {
                    return $resolved;
                }
                $scope = 'event';
                $scopeRef = $resolved;
                $eventForAccess = $resolved;
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'Scope erforderlich: quote_item_id|quote_item_uuid ODER quote_id|quote_uuid|quote_token ODER event_id|event_uuid|event_number.');
            }

            if (!$eventForAccess || !$context->user->teams()->where('teams.id', $eventForAccess->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf den Scope.');
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

            // ----- QuoteItem-IDs im Scope sammeln -----
            $itemIds = [];
            if ($scope === 'item') {
                $itemIds = [(int) $scopeRef->id];
            } elseif ($scope === 'quote') {
                // Alle QuoteItems des Events ueber Tage einsammeln. Da QuoteItem nicht direkt
                // an Quote haengt, sind im "Quote-Scope" alle Items des zugehoerigen Events
                // gemeint (Standard-Interpretation: ein Event hat genau EIN aktuelles Angebot).
                $itemIds = QuoteItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $scopeRef->event_id))->pluck('id')->all();
            } elseif ($scope === 'event') {
                $itemIds = QuoteItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $scopeRef->id))->pluck('id')->all();
            }
            if (empty($itemIds)) {
                return ToolResult::success([
                    'scope'        => $scope,
                    'count'        => 0,
                    'affected_ids' => [],
                    'set_fields'   => array_keys($update),
                    'message'      => 'Keine QuoteItems im Scope.',
                ]);
            }

            // ----- Filter -----
            $query = QuotePosition::whereIn('quote_item_id', $itemIds);
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
            $confirmWide = (bool) ($arguments['confirm_scope_wide'] ?? ($arguments['confirm_item_wide'] ?? false));
            if (!$hasFilter && !$confirmWide) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein Filter angegeben. Setze position_ids[]/gruppe/gruppe_contains/name_contains ODER confirm_scope_wide=true (gilt im gesamten Scope).');
            }

            $positions = $query->get();
            if ($positions->isEmpty()) {
                return ToolResult::success([
                    'scope'        => $scope,
                    'count'        => 0,
                    'affected_ids' => [],
                    'set_fields'   => array_keys($update),
                    'message'      => 'Keine Positionen entsprechen dem Filter.',
                ]);
            }

            $known = [
                'event_id', 'event_uuid', 'event_number',
                'quote_id', 'quote_uuid', 'quote_token',
                'quote_item_id', 'quote_item_uuid',
                'position_ids', 'gruppe', 'gruppe_contains', 'name_contains',
                'confirm_scope_wide', 'confirm_item_wide', 'set',
            ];
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            // ----- Update ausfuehren -----
            $affected = [];
            $touchedItemIds = [];
            foreach ($positions as $pos) {
                $rowUpdate = $update;
                if (!array_key_exists('gesamt', $rowUpdate) && array_key_exists('preis', $rowUpdate)) {
                    $rowUpdate['gesamt'] = (float) $pos->anz * (float) $rowUpdate['preis'];
                }
                $pos->update($rowUpdate);
                $affected[] = $pos->id;
                $touchedItemIds[$pos->quote_item_id] = true;
            }

            // ----- Recalc aller betroffenen QuoteItems -----
            $touchedItems = QuoteItem::whereIn('id', array_keys($touchedItemIds))->get();
            foreach ($touchedItems as $ti) {
                $this->recalcQuoteItem($ti);
            }

            return ToolResult::success([
                'scope'           => $scope,
                'event_id'        => $eventForAccess->id,
                'count'           => count($affected),
                'affected_ids'    => $affected,
                'recalculated_quote_items' => array_values(array_keys($touchedItemIds)),
                'set_fields'      => array_keys($update),
                'aliases_applied' => $aliasesApplied,
                'ignored_fields'  => $ignored,
                'message'         => count($affected) . ' Position(en) im ' . $scope . '-Scope aktualisiert (Recalc fuer ' . count($touchedItemIds) . ' Vorgaenge).',
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
