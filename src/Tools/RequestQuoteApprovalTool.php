<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\User;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Tools\Concerns\ResolvesQuote;

/**
 * Fordert eine Freigabe des Angebots durch einen anderen Team-User an.
 * Setzt approval_status='pending' + approver_id + Timestamps.
 */
class RequestQuoteApprovalTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuote;

    public function getName(): string
    {
        return 'events.quotes.approval.REQUEST';
    }

    public function getDescription(): string
    {
        return 'POST /events/quotes/{id}/approval/request - Fordert eine Freigabe an. '
            . 'Pflicht: approver_user_id (muss Mitglied des gleichen Teams sein). '
            . 'Optional: comment.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->quoteSelectorSchema(), [
                'approver_user_id' => ['type' => 'integer', 'description' => 'User-ID des Freigebers (Pflicht).'],
                'comment'          => ['type' => 'string',  'description' => 'Optionale Begruendung.'],
            ]),
            'required' => ['approver_user_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $quote = $this->resolveQuote($arguments, $context);
            if ($quote instanceof ToolResult) {
                return $quote;
            }

            $approverId = (int) ($arguments['approver_user_id'] ?? 0);
            if ($approverId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'approver_user_id ist erforderlich.');
            }

            $approver = User::find($approverId);
            if (!$approver) {
                return ToolResult::error('VALIDATION_ERROR', 'Freigeber-User nicht gefunden.');
            }
            // Approver muss im gleichen Team sein wie der Quote
            if (!$approver->teams()->where('teams.id', $quote->team_id)->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Freigeber ist nicht Mitglied des Teams.');
            }

            $quote->update([
                'approval_status'       => 'pending',
                'approver_id'           => $approver->id,
                'approval_requested_by' => Auth::id() ?: $context->user?->id,
                'approval_requested_at' => now(),
                'approval_decided_at'   => null,
                'approval_comment'      => isset($arguments['comment']) && trim((string) $arguments['comment']) !== ''
                    ? trim((string) $arguments['comment'])
                    : null,
            ]);

            $event = $quote->event;
            if ($event) {
                ActivityLogger::log($event, 'quote', "Angebot v{$quote->version}: Freigabe angefordert bei {$approver->name} (via Tool)");
            }

            return ToolResult::success([
                'quote_id'        => $quote->id,
                'version'         => $quote->version,
                'approval_status' => 'pending',
                'approver'        => ['id' => $approver->id, 'name' => $approver->name],
                'message'         => "Freigabe bei {$approver->name} angefordert.",
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
