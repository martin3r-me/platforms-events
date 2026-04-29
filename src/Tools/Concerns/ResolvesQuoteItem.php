<?php

namespace Platform\Events\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\QuoteItem;

/**
 * Helfer, um ein QuoteItem ueber quote_item_id|quote_item_uuid zu laden
 * und Team-Zugriff zu pruefen.
 */
trait ResolvesQuoteItem
{
    /**
     * @return QuoteItem|ToolResult
     */
    protected function resolveQuoteItem(array $arguments, ToolContext $context): QuoteItem|ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
        }

        $item = null;
        if (!empty($arguments['quote_item_id'])) {
            $item = QuoteItem::find((int) $arguments['quote_item_id']);
        } elseif (!empty($arguments['quote_item_uuid'])) {
            $item = QuoteItem::where('uuid', $arguments['quote_item_uuid'])->first();
        } else {
            return ToolResult::error('VALIDATION_ERROR', 'quote_item_id oder quote_item_uuid ist erforderlich.');
        }

        if (!$item) {
            return ToolResult::error('QUOTE_ITEM_NOT_FOUND', 'Vorgang nicht gefunden.');
        }

        $event = $item->eventDay?->event;
        if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
            return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf den Vorgang.');
        }

        return $item;
    }

    protected function quoteItemSelectorSchema(): array
    {
        return [
            'quote_item_id'   => ['type' => 'integer', 'description' => 'ID des Vorgangs.'],
            'quote_item_uuid' => ['type' => 'string',  'description' => 'UUID des Vorgangs.'],
        ];
    }
}
