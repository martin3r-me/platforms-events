<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Tools\Concerns\ResolvesQuote;

/**
 * Liefert ein Angebot inkl. effektivem attach_floor_plans-Wert und optional
 * den enthaltenen Vorgaengen / Positionen (vom Event-Tag, da Items am Event_day haengen).
 */
class GetQuoteTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuote;

    public function getName(): string
    {
        return 'events.quotes.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/quotes/{id} - Liefert ein Angebot. Identifikation: quote_id|quote_uuid|quote_token. '
            . 'Optional include_items=true gibt QuoteItems mit Aggregat-Counts zurueck.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->quoteSelectorSchema(), [
                'include_items' => ['type' => 'boolean', 'description' => 'Default false. true = Items pro Tag mitliefern.'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $quote = $this->resolveQuote($arguments, $context);
            if ($quote instanceof ToolResult) {
                return $quote;
            }

            $event = $quote->event;
            $payload = [
                'id'                 => $quote->id,
                'uuid'               => $quote->uuid,
                'token'              => $quote->token,
                'event_id'           => $quote->event_id,
                'event_number'       => $event?->event_number,
                'version'            => $quote->version,
                'parent_id'          => $quote->parent_id,
                'is_current'         => (bool) $quote->is_current,
                'status'             => $quote->status,
                'valid_until'        => $quote->valid_until?->toDateString(),
                'sent_at'            => $quote->sent_at?->toIso8601String(),
                'responded_at'       => $quote->responded_at?->toIso8601String(),
                'response_note'      => $quote->response_note,
                'last_viewed_at'     => $quote->last_viewed_at?->toIso8601String(),
                'view_count'         => (int) ($quote->view_count ?? 0),
                'attach_floor_plans' => $quote->attach_floor_plans, // Override (null|bool)
                'effective_attach_floor_plans' => $quote->shouldAttachFloorPlans(),
                'approval_status'    => $quote->approval_status,
                'approver_id'        => $quote->approver_id,
                'approval_decided_at'=> $quote->approval_decided_at?->toIso8601String(),
                'approval_comment'   => $quote->approval_comment,
                'created_at'         => $quote->created_at?->toIso8601String(),
            ];

            if (!empty($arguments['include_items']) && $event) {
                $items = \Platform\Events\Models\QuoteItem::whereIn('event_day_id', $event->days->pluck('id'))
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn ($i) => [
                        'id'            => $i->id,
                        'uuid'          => $i->uuid,
                        'event_day_id'  => $i->event_day_id,
                        'typ'           => $i->typ,
                        'status'        => $i->status,
                        'mwst'          => $i->mwst,
                        'beverage_mode' => $i->beverage_mode,
                        'artikel'       => (int) $i->artikel,
                        'positionen'    => (int) $i->positionen,
                        'umsatz'        => (float) $i->umsatz,
                    ])
                    ->all();
                $payload['items'] = $items;
            }

            return ToolResult::success(['quote' => $payload]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Lesen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['events', 'quote', 'read'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
