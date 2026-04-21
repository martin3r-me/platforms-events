<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Listet Angebots-Positionen (Artikel) zu einem QuoteItem (Vorgang).
 */
class ListQuotePositionsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.quote-positions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/quote-items/{id}/positions - Listet Positionen eines Angebots-Vorgangs (QuoteItem). '
            . 'Identifikation via quote_item_id oder quote_item_uuid.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'quote_item_id'   => ['type' => 'integer', 'description' => 'ID des QuoteItems.'],
                    'quote_item_uuid' => ['type' => 'string',  'description' => 'UUID des QuoteItems.'],
                    'gruppe'          => ['type' => 'string',  'description' => 'Optional: Gruppe-Filter.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $quoteItem = null;
            if (!empty($arguments['quote_item_id'])) {
                $quoteItem = QuoteItem::find($arguments['quote_item_id']);
            } elseif (!empty($arguments['quote_item_uuid'])) {
                $quoteItem = QuoteItem::where('uuid', $arguments['quote_item_uuid'])->first();
            }
            if (!$quoteItem) {
                return ToolResult::error('NOT_FOUND', 'QuoteItem nicht gefunden.');
            }

            // Team-Check via EventDay→Event
            $event = $quoteItem->eventDay?->event;
            if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf das Event.');
            }

            $query = QuotePosition::where('quote_item_id', $quoteItem->id);
            if (!empty($arguments['gruppe'])) {
                $query->where('gruppe', $arguments['gruppe']);
            }
            $this->applyStandardFilters($query, $arguments, ['gruppe', 'name', 'mwst']);
            $this->applyStandardSearch($query, $arguments, ['name', 'gruppe', 'bemerkung']);
            $this->applyStandardSort($query, $arguments, ['sort_order', 'created_at'], 'sort_order', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $positions = $query->get()->map(fn (QuotePosition $p) => [
                'id'         => $p->id,
                'uuid'       => $p->uuid,
                'gruppe'     => $p->gruppe,
                'name'       => $p->name,
                'anz'        => $p->anz,
                'anz2'       => $p->anz2,
                'uhrzeit'    => $p->uhrzeit,
                'bis'        => $p->bis,
                'gebinde'    => $p->gebinde,
                'ek'         => (float) $p->ek,
                'preis'      => (float) $p->preis,
                'mwst'       => $p->mwst,
                'gesamt'     => (float) $p->gesamt,
                'bemerkung'  => $p->bemerkung,
                'sort_order' => $p->sort_order,
            ])->toArray();

            return ToolResult::success([
                'positions'     => $positions,
                'count'         => count($positions),
                'quote_item_id' => $quoteItem->id,
                'typ'           => $quoteItem->typ,
                'message'       => count($positions) . " Position(en) für {$quoteItem->typ}.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query', 'tags' => ['events', 'quote', 'position', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true,
        ];
    }
}
