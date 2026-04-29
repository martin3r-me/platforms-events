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
 * Synchronisiert eine bestehende Bestellung mit den aktuellen Angebots-
 * Positionen. Liefert null wenn keine Bestellung verbunden ist.
 */
class SyncQuoteItemToOrderTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuoteItem;

    public function getName(): string
    {
        return 'events.quote-items.SYNC_TO_ORDER';
    }

    public function getDescription(): string
    {
        return 'POST /events/quote-items/{id}/sync-to-order - Aktualisiert eine bereits konvertierte Bestellung mit den '
            . 'aktuellen Angebots-Positionen. Wenn keine Bestellung existiert, wird ein 404-aehnlicher Fehler zurueckgegeben.';
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

            $orderItem = QuoteOrderConverter::syncItem($item);
            if (!$orderItem) {
                return ToolResult::error('NO_ORDER_LINKED', 'Zu diesem Vorgang gibt es noch keinen verknuepften Bestell-Vorgang.');
            }

            $event = $item->eventDay?->event;
            if ($event) {
                ActivityLogger::log($event, 'quote', "Bestellung \"{$orderItem->typ}\" via Tool mit Angebot synchronisiert");
            }

            return ToolResult::success([
                'quote_item_id' => $item->id,
                'order_item'    => ['id' => $orderItem->id, 'uuid' => $orderItem->uuid, 'typ' => $orderItem->typ],
                'message'       => "Bestellung mit Angebot synchronisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'order', 'sync'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
