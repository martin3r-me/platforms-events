<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\FlatRateRule;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Services\FlatRateApplicator;
use Platform\Events\Tools\Concerns\RecalculatesQuoteItem;
use Platform\Events\Tools\Concerns\ResolvesQuoteItem;

/**
 * Wendet eine Pauschal-Regel (FlatRateRule) auf einen Vorgang an. Erzeugt eine
 * Pauschal-Position und eine FlatRateApplication (Audit-Trail).
 */
class ApplyFlatRateTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuoteItem;
    use RecalculatesQuoteItem;

    public function getName(): string
    {
        return 'events.quotes.APPLY_FLAT_RATE';
    }

    public function getDescription(): string
    {
        return 'POST /events/quote-items/{id}/apply-flat-rate - Wendet eine Pauschal-Regel auf einen Vorgang an. '
            . 'Pflicht: quote_item_id|quote_item_uuid + rule_id|rule_name. Recalc automatisch.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->quoteItemSelectorSchema(), [
                'rule_id'   => ['type' => 'integer', 'description' => 'ID der FlatRateRule.'],
                'rule_name' => ['type' => 'string',  'description' => 'Alternativ: exakter Name (Team-eindeutig).'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $item = $this->resolveQuoteItem($arguments, $context);
            if ($item instanceof ToolResult) {
                return $item;
            }

            $rule = null;
            if (!empty($arguments['rule_id'])) {
                $rule = FlatRateRule::where('team_id', $item->team_id)->find((int) $arguments['rule_id']);
            } elseif (!empty($arguments['rule_name'])) {
                $rule = FlatRateRule::where('team_id', $item->team_id)->where('name', trim((string) $arguments['rule_name']))->first();
            }
            if (!$rule) {
                return ToolResult::error('VALIDATION_ERROR', 'rule_id oder rule_name ist erforderlich (Team-Match).');
            }
            if (!$rule->is_active) {
                return ToolResult::error('VALIDATION_ERROR', 'Pauschal-Regel ist deaktiviert.');
            }

            $result = FlatRateApplicator::apply($rule, $item);
            $this->recalcQuoteItem($item);

            $event = $item->eventDay?->event;
            if ($event) {
                ActivityLogger::log($event, 'quote', "Pauschal-Regel \"{$rule->name}\" via Tool angewendet");
            }

            return ToolResult::success([
                'quote_item_id' => $item->id,
                'rule'          => ['id' => $rule->id, 'name' => $rule->name],
                'application'   => $result,
                'message'       => "Pauschale «{$rule->name}» angewendet.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'flat-rate', 'apply'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => false, 'side_effects' => ['creates', 'updates'],
        ];
    }
}
