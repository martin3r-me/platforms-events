<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\QuotePosition;
use Platform\Events\Tools\Concerns\RecalculatesQuoteItem;

/**
 * Soft-Delete einer Angebots-Position. Recalc des QuoteItems (artikel,
 * positionen, umsatz) erfolgt automatisch.
 */
class DeleteQuotePositionTool implements ToolContract, ToolMetadataContract
{
    use RecalculatesQuoteItem;

    public function getName(): string
    {
        return 'events.quote-positions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/quote-positions/{id} - Soft-Delete einer Angebots-Position. '
            . 'Identifikation: position_id ODER uuid. Recalc des QuoteItems erfolgt automatisch.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'position_id' => ['type' => 'integer'],
                'uuid'        => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            $query = QuotePosition::query();
            if (!empty($arguments['position_id'])) {
                $query->where('id', (int) $arguments['position_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'position_id oder uuid ist erforderlich.');
            }
            $position = $query->first();
            if (!$position) {
                return ToolResult::error('POSITION_NOT_FOUND', 'Angebots-Position nicht gefunden.');
            }
            $event = $position->quoteItem?->eventDay?->event;
            if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf diese Position.');
            }

            $id = $position->id;
            $name = $position->name;
            $quoteItem = $position->quoteItem;

            $position->delete();

            // Recalc nach dem Delete.
            if ($quoteItem) {
                $this->recalcQuoteItem($quoteItem);
            }

            return ToolResult::success([
                'id'            => $id,
                'name'          => $name,
                'quote_item_id' => $quoteItem?->id,
                'message'       => "Position '{$name}' geloescht (soft).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'position', 'delete'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'destructive', 'idempotent' => false, 'side_effects' => ['deletes', 'updates'],
        ];
    }
}
