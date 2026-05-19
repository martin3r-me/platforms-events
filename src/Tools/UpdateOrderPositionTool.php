<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Tools\Concerns\CollectsValidationErrors;
use Platform\Events\Tools\Concerns\RecalculatesOrderItem;

/**
 * Aktualisiert eine einzelne Bestell-Position. Nach dem Update werden die
 * Aggregat-Felder des OrderItems (artikel, positionen, einkauf) automatisch
 * neu berechnet.
 */
class UpdateOrderPositionTool implements ToolContract, ToolMetadataContract
{
    use CollectsValidationErrors;
    use RecalculatesOrderItem;

    protected const STRING_FIELDS = [
        'gruppe', 'name', 'anz', 'anz2', 'uhrzeit', 'bis', 'gebinde',
        'mwst', 'bemerkung', 'inhalt', 'procurement_type',
    ];
    protected const NUMERIC_FIELDS = ['ek', 'preis', 'gesamt'];
    protected const INT_FIELDS     = ['sort_order'];

    /** Aliases analog zum Quote-Pendant. */
    protected const FIELD_ALIASES = [
        'price_net' => 'preis',
        'price'     => 'preis',
        'vk'        => 'preis',
        'tax_rate'  => 'mwst',
        'unit'      => 'gebinde',
    ];

    public function getName(): string
    {
        return 'events.order-positions.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/order-positions/{id} - Aktualisiert eine Bestell-Position. '
            . 'Identifikation: position_id ODER uuid. '
            . 'Felder (alle optional): gruppe, name, anz, anz2, uhrzeit, bis, gebinde (Alias unit), '
            . 'inhalt, ek, preis (Alias price_net|price|vk), mwst ("0%"|"7%"|"19%"; Alias tax_rate), '
            . 'gesamt (auto = anz × ek wenn weggelassen), bemerkung, sort_order, procurement_type. '
            . 'Recalc des OrderItems (artikel/positionen/einkauf) erfolgt automatisch. '
            . 'OrderPositions haben kein basis_ek — Einkauf wird in `ek` gefuehrt.';
    }

    public function getSchema(): array
    {
        $props = [
            'position_id' => ['type' => 'integer'],
            'uuid'        => ['type' => 'string'],
        ];
        foreach (self::STRING_FIELDS as $f)  $props[$f] = ['type' => 'string'];
        foreach (self::NUMERIC_FIELDS as $f) $props[$f] = ['type' => 'number'];
        foreach (self::INT_FIELDS as $f)     $props[$f] = ['type' => 'integer'];
        foreach (self::FIELD_ALIASES as $alias => $primary) {
            $type = in_array($primary, self::NUMERIC_FIELDS, true) ? 'number' : 'string';
            $props[$alias] = ['type' => $type, 'description' => "Alias fuer {$primary}."];
        }
        return ['type' => 'object', 'properties' => $props];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $query = OrderPosition::query();
            if (!empty($arguments['position_id'])) {
                $query->where('id', (int) $arguments['position_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'position_id oder uuid ist erforderlich.');
            }
            $position = $query->first();
            if (!$position) {
                return ToolResult::error('POSITION_NOT_FOUND', 'Bestell-Position nicht gefunden.');
            }
            $event = $position->orderItem?->eventDay?->event;
            if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diese Position.');
            }

            // Aliases mappen.
            $aliasesApplied = [];
            foreach (self::FIELD_ALIASES as $alias => $primary) {
                if (array_key_exists($alias, $arguments)
                    && (!array_key_exists($primary, $arguments) || $arguments[$primary] === null || $arguments[$primary] === '')
                ) {
                    $arguments[$primary] = $arguments[$alias];
                    $aliasesApplied[] = "{$alias}→{$primary}";
                }
            }

            // Validation
            $errors = [];
            if (array_key_exists('mwst', $arguments)
                && $arguments['mwst'] !== null && $arguments['mwst'] !== ''
                && !in_array($arguments['mwst'], ['0%', '7%', '19%'], true)
            ) {
                $errors[] = $this->validationError('mwst', 'mwst muss einer von: "0%" | "7%" | "19%".');
            }
            if (!empty($errors)) {
                return $this->validationFailure($errors);
            }

            $update = [];
            foreach (self::STRING_FIELDS as $f) {
                if (array_key_exists($f, $arguments)) {
                    $value = $arguments[$f];
                    if ($f === 'procurement_type') {
                        $update[$f] = ($value === null || $value === '') ? null : (string) $value;
                    } else {
                        $update[$f] = $value === null ? null : (string) $value;
                    }
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

            // Auto-Recompute gesamt, wenn anz/ek geaendert wurden, gesamt aber nicht
            // (OrderPosition rechnet ueber ek, anders als QuotePosition mit preis).
            if (!array_key_exists('gesamt', $arguments)
                && (array_key_exists('anz', $update) || array_key_exists('ek', $update))
            ) {
                $newAnz = (float) ($update['anz'] ?? $position->anz);
                $newEk  = (float) ($update['ek']  ?? $position->ek);
                $update['gesamt'] = $newAnz * $newEk;
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $known = array_merge(
                ['position_id', 'uuid'],
                self::STRING_FIELDS, self::NUMERIC_FIELDS, self::INT_FIELDS,
                array_keys(self::FIELD_ALIASES),
            );
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            $position->update($update);

            // Recalc des OrderItems (artikel ohne Bausteine, positionen, einkauf).
            $this->recalcOrderItem($position->orderItem);

            return ToolResult::success([
                'id'              => $position->id,
                'uuid'            => $position->uuid,
                'order_item_id'   => $position->order_item_id,
                'gruppe'          => $position->gruppe,
                'name'            => $position->name,
                'anz'             => $position->anz,
                'ek'              => (float) $position->ek,
                'preis'           => (float) $position->preis,
                'gesamt'          => (float) $position->gesamt,
                'mwst'            => $position->mwst,
                'sort_order'      => $position->sort_order,
                'procurement_type'=> $position->procurement_type,
                'updated_fields'  => array_keys($update),
                'aliases_applied' => $aliasesApplied,
                'ignored_fields'  => $ignored,
                '_field_hints'    => [
                    'ek'      => 'OrderPosition fuehrt den Einkauf ausschliesslich in `ek` — kein basis_ek.',
                    'preis'   => 'Aliases: price_net | price | vk.',
                    'mwst'    => 'Strikt: "0%" | "7%" | "19%". Alias: tax_rate.',
                    'gesamt'  => 'Auto = anz × ek, wenn nicht explizit gesetzt und anz/ek geaendert wurden.',
                ],
                'message'         => 'Position aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'order', 'position', 'update'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
