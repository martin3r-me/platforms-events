<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Tools\Concerns\ResolvesQuote;

/**
 * Zieht eine offene Freigabe-Anfrage zurueck (approval_status='none').
 */
class CancelQuoteApprovalRequestTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuote;

    public function getName(): string
    {
        return 'events.quotes.approval.CANCEL';
    }

    public function getDescription(): string
    {
        return 'POST /events/quotes/{id}/approval/cancel - Zieht eine ausstehende Freigabe-Anfrage zurueck. '
            . 'Setzt approval_status=none und leert approver/Timestamps.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $this->quoteSelectorSchema(),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $quote = $this->resolveQuote($arguments, $context);
            if ($quote instanceof ToolResult) {
                return $quote;
            }

            if ($quote->approval_status !== 'pending') {
                return ToolResult::error('VALIDATION_ERROR', 'Es liegt keine ausstehende Anfrage vor (status: ' . ($quote->approval_status ?? 'none') . ').');
            }

            $quote->update([
                'approval_status'       => 'none',
                'approver_id'           => null,
                'approval_requested_by' => null,
                'approval_requested_at' => null,
                'approval_decided_at'   => null,
                'approval_comment'      => null,
            ]);

            $event = $quote->event;
            if ($event) {
                ActivityLogger::log($event, 'quote', "Angebot v{$quote->version}: Freigabe-Anfrage zurueckgezogen (via Tool)");
            }

            return ToolResult::success([
                'quote_id'        => $quote->id,
                'version'         => $quote->version,
                'approval_status' => 'none',
                'message'         => "Anfrage zurueckgezogen.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'approval'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
