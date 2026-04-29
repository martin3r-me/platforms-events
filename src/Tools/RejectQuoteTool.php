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
 * Lehnt die Freigabe eines Angebots ab. Nur der zugewiesene approver_id darf
 * reject – Spiegelung der Livewire-Logik (rejectQuote).
 */
class RejectQuoteTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuote;

    public function getName(): string
    {
        return 'events.quotes.approval.REJECT';
    }

    public function getDescription(): string
    {
        return 'POST /events/quotes/{id}/approval/reject - Lehnt die Freigabe ab. '
            . 'Setzt approval_status=rejected + approval_decided_at. '
            . 'Optional: comment.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->quoteSelectorSchema(), [
                'comment' => ['type' => 'string', 'description' => 'Optionale Begruendung der Ablehnung.'],
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

            $actorId = (int) (Auth::id() ?: $context->user?->id);
            if (!$actorId) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }
            if ((int) $quote->approver_id !== $actorId) {
                return ToolResult::error('ACCESS_DENIED', 'Nur der zugewiesene Freigeber darf das Angebot ablehnen.');
            }
            if ($quote->approval_status !== 'pending') {
                return ToolResult::error('VALIDATION_ERROR', 'Approval-Status ist nicht "pending".');
            }

            $update = [
                'approval_status'     => 'rejected',
                'approval_decided_at' => now(),
            ];
            if (isset($arguments['comment']) && trim((string) $arguments['comment']) !== '') {
                $update['approval_comment'] = trim((string) $arguments['comment']);
            }
            $quote->update($update);

            $event = $quote->event;
            if ($event) {
                ActivityLogger::log($event, 'quote', "Angebot v{$quote->version}: Freigabe abgelehnt (via Tool)");
            }

            return ToolResult::success([
                'quote_id'        => $quote->id,
                'version'         => $quote->version,
                'approval_status' => 'rejected',
                'message'         => "Angebot v{$quote->version} abgelehnt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'approval', 'reject'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'moderate', 'idempotent' => true, 'side_effects' => ['updates'],
        ];
    }
}
