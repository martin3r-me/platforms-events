<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Tools\Concerns\ResolvesQuote;

/**
 * Erteilt die Freigabe fuer ein Angebot. Nur der zugewiesene approver_id darf
 * approven – Spiegelung der Livewire-Logik (approveQuote).
 */
class ApproveQuoteTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuote;

    public function getName(): string
    {
        return 'events.quotes.approval.APPROVE';
    }

    public function getDescription(): string
    {
        return 'POST /events/quotes/{id}/approval/approve - Erteilt die Freigabe. '
            . 'Setzt approval_status=approved + approval_decided_at. '
            . 'Nur der vorher per request angeforderte approver_id darf approven.';
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

            $actorId = (int) (Auth::id() ?: $context->user?->id);
            if (!$actorId) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            if ((int) $quote->approver_id !== $actorId) {
                return ToolResult::error('ACCESS_DENIED', 'Nur der zugewiesene Freigeber darf das Angebot freigeben.');
            }
            if ($quote->approval_status !== 'pending') {
                return ToolResult::error('VALIDATION_ERROR', 'Approval-Status ist nicht "pending".');
            }

            $quote->update([
                'approval_status'     => 'approved',
                'approval_decided_at' => now(),
            ]);

            $event = $quote->event;
            if ($event) {
                ActivityLogger::log($event, 'quote', "Angebot v{$quote->version}: Freigegeben (via Tool)");
            }

            return ToolResult::success([
                'quote_id'        => $quote->id,
                'version'         => $quote->version,
                'approval_status' => 'approved',
                'message'         => "Angebot v{$quote->version} freigegeben.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'approval', 'approve'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'moderate', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
