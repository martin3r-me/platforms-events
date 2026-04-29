<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Services\QuoteOrderConverter;
use Platform\Events\Tools\Concerns\ResolvesQuoteItem;

/**
 * Ueberfuehrt einen Angebots-Vorgang (QuoteItem) in einen Bestell-Vorgang
 * (OrderItem). Spiegelt Livewire-Action convertQuoteItemToOrder().
 */
class ConvertQuoteItemToOrderTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuoteItem;

    public function getName(): string
    {
        return 'events.quote-items.CONVERT_TO_ORDER';
    }

    public function getDescription(): string
    {
        return 'POST /events/quote-items/{id}/convert-to-order - Erzeugt einen Bestell-Vorgang aus einem Angebots-Vorgang '
            . '(inkl. Positionen). Identifikation via quote_item_id|quote_item_uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $this->quoteItemSelectorSchema(),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $item = $this->resolveQuoteItem($arguments, $context);
            if ($item instanceof ToolResult) {
                return $item;
            }

            $orderItem = QuoteOrderConverter::convertItem($item);

            $event = $item->eventDay?->event;
            if ($event) {
                ActivityLogger::log($event, 'quote', "Vorgang \"{$item->typ}\" via Tool in Bestellung ueberfuehrt");
            }

            return ToolResult::success([
                'quote_item_id' => $item->id,
                'order_item' => [
                    'id'   => $orderItem->id,
                    'uuid' => $orderItem->uuid,
                    'typ'  => $orderItem->typ,
                ],
                'message' => "Vorgang in Bestellung ueberfuehrt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'order', 'convert'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => false, 'side_effects' => ['creates'],
        ];
    }
}
