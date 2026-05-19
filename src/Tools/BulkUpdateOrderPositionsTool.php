<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\RecalculatesOrderItem;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Massen-Update mehrerer Bestell-Positionen innerhalb eines OrderItems oder
 * eventweit. Filter + Setzwerte werden in einem Call uebergeben — Recalc der
 * betroffenen OrderItems erfolgt automatisch.
 */
class BulkUpdateOrderPositionsTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use RecalculatesOrderItem;
    use ResolvesEvent;

    protected const SETTABLE_STRING_FIELDS = [
        'gruppe', 'mwst', 'gebinde', 'bemerkung', 'inhalt', 'procurement_type',
    ];
    protected const SETTABLE_NUMERIC_FIELDS = ['ek', 'preis', 'gesamt'];

    protected const FIELD_ALIASES = [
        'price_net' => 'preis',
        'price'     => 'preis',
        'vk'        => 'preis',
        'tax_rate'  => 'mwst',
        'unit'      => 'gebinde',
    ];

    public function getName(): string
    {
        return 'events.order-positions.bulk.PATCH';
    }

    public function getDescription(): string
    {
        return 'POST /events/order-positions/bulk - Massen-Update von Bestell-Positionen. '
            . 'SCOPE (genau einer): '
            . '(1) order_item_id|order_item_uuid – nur Positionen dieses Vorgangs. '
            . '(2) event_id|event_uuid|event_number – alle Positionen aller Order-Items aller Tage des Events. '
            . 'INNERHALB des Scopes mind. ein Filter ODER confirm_scope_wide=true: '
            . 'position_ids[], gruppe (exakt), gruppe_contains, name_contains. '
            . 'Setzwerte unter "set" (mind. einer): gruppe, mwst, gebinde, bemerkung, inhalt, '
            . 'procurement_type, ek, preis (Aliases: price_net|price|vk), gesamt, sort_order. '
            . 'Aliases werden in set akzeptiert. Recalc aller betroffenen OrderItems am Ende automatisch. '
            . 'Hinweis: OrderPositions haben kein basis_ek.';
    }

    public function getSchema(): array
    {
        $setProps = [];
        foreach (self::SETTABLE_STRING_FIELDS as $f)  $setProps[$f] = ['type' => 'string'];
        foreach (self::SETTABLE_NUMERIC_FIELDS as $f) $setProps[$f] = ['type' => 'number'];
        $setProps['sort_order'] = ['type' => 'integer'];
        foreach (self::FIELD_ALIASES as $alias => $primary) {
            $type = in_array($primary, self::SETTABLE_NUMERIC_FIELDS, true) ? 'number' : 'string';
            $setProps[$alias] = ['type' => $type, 'description' => "Alias fuer {$primary}."];
        }

        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'order_item_id'      => ['type' => 'integer', 'description' => 'Scope: nur Positionen dieses Vorgangs.'],
                'order_item_uuid'    => ['type' => 'string'],
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

            // ----- Scope aufloesen (genau einer) -----
            $scope = null; // 'item' | 'event'
            $scopeRef = null;
            $eventForAccess = null;

            if (!empty($arguments['order_item_id']) || !empty($arguments['order_item_uuid'])) {
                $scope = 'item';
                $scopeRef = !empty($arguments['order_item_id'])
                    ? OrderItem::find((int) $arguments['order_item_id'])
                    : OrderItem::where('uuid', $arguments['order_item_uuid'])->first();
                if (!$scopeRef) {
                    return ToolResult::error('ORDER_ITEM_NOT_FOUND', 'Order-Vorgang nicht gefunden.');
                }
                $eventForAccess = $scopeRef->eventDay?->event;
            } elseif (!empty($arguments['event_id']) || !empty($arguments['event_uuid']) || !empty($arguments['event_number'])) {
                $resolved = $this->resolveEvent($arguments, $context);
                if ($resolved instanceof ToolResult) {
                    return $resolved;
                }
                $scope = 'event';
                $scopeRef = $resolved;
                $eventForAccess = $resolved;
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'Scope erforderlich: order_item_id|order_item_uuid ODER event_id|event_uuid|event_number.');
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

            // Validation
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
                    $update[$f] = ($value === null || $value === '') && $f === 'procurement_type'
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

            // ----- OrderItem-IDs im Scope sammeln -----
            $itemIds = [];
            if ($scope === 'item') {
                $itemIds = [(int) $scopeRef->id];
            } elseif ($scope === 'event') {
                $itemIds = OrderItem::whereHas('eventDay', fn ($q) => $q->where('event_id', $scopeRef->id))->pluck('id')->all();
            }
            if (empty($itemIds)) {
                return ToolResult::success([
                    'scope'        => $scope,
                    'count'        => 0,
                    'affected_ids' => [],
                    'set_fields'   => array_keys($update),
                    'message'      => 'Keine OrderItems im Scope.',
                ]);
            }

            // ----- Filter -----
            $query = OrderPosition::whereIn('order_item_id', $itemIds);
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
                return ToolResult::error('VALIDATION_ERROR', 'Kein Filter angegeben. Setze position_ids[]/gruppe/gruppe_contains/name_contains ODER confirm_scope_wide=true.');
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
                'order_item_id', 'order_item_uuid',
                'position_ids', 'gruppe', 'gruppe_contains', 'name_contains',
                'confirm_scope_wide', 'confirm_item_wide', 'set',
            ];
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            // ----- Update ausfuehren -----
            $affected = [];
            $touchedItemIds = [];
            foreach ($positions as $pos) {
                $rowUpdate = $update;
                // Auto-Recompute gesamt aus anz × ek, wenn ek geaendert wurde
                // (Order rechnet ueber ek, nicht preis wie beim Quote).
                if (!array_key_exists('gesamt', $rowUpdate) && array_key_exists('ek', $rowUpdate)) {
                    $rowUpdate['gesamt'] = (float) $pos->anz * (float) $rowUpdate['ek'];
                }
                $pos->update($rowUpdate);
                $affected[] = $pos->id;
                $touchedItemIds[$pos->order_item_id] = true;
            }

            // ----- Recalc aller betroffenen OrderItems -----
            $touchedItems = OrderItem::whereIn('id', array_keys($touchedItemIds))->get();
            foreach ($touchedItems as $ti) {
                $this->recalcOrderItem($ti);
            }

            return ToolResult::success([
                'scope'           => $scope,
                'event_id'        => $eventForAccess->id,
                'count'           => count($affected),
                'affected_ids'    => $affected,
                'recalculated_order_items' => array_values(array_keys($touchedItemIds)),
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
            'category' => 'action', 'tags' => ['events', 'order', 'position', 'update', 'bulk'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
