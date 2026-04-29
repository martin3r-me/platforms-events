<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Tools\Concerns\ResolvesQuote;

/**
 * Aktualisiert ein Angebot. Status-Wechsel setzen ggf. sent_at/responded_at
 * automatisch. attach_floor_plans-Override ist explizit setzbar (true/false/null).
 */
class UpdateQuoteTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuote;

    public function getName(): string
    {
        return 'events.quotes.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/quotes/{id} - Aktualisiert ein Angebot. Identifikation: quote_id|quote_uuid|quote_token. '
            . 'status-Wechsel: sent setzt sent_at, accepted/rejected setzt responded_at. '
            . 'attach_floor_plans: true/false setzt Override; null = Team-Default. '
            . 'Felder: status, valid_until, response_note, attach_floor_plans, is_current.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->quoteSelectorSchema(), [
                'status'             => ['type' => 'string', 'description' => 'draft|sent|accepted|rejected'],
                'valid_until'        => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'response_note'      => ['type' => 'string'],
                'attach_floor_plans' => ['type' => ['boolean', 'null'], 'description' => 'true/false setzt Override; null = Team-Default.'],
                'is_current'         => ['type' => 'boolean', 'description' => 'Wenn true: macht alle anderen Quotes des Events auf is_current=false.'],
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

            $update = [];
            $oldStatus = $quote->status;

            if (array_key_exists('status', $arguments) && is_string($arguments['status']) && $arguments['status'] !== '') {
                $newStatus = $arguments['status'];
                $update['status'] = $newStatus;

                if ($newStatus === 'sent' && empty($quote->sent_at)) {
                    $update['sent_at'] = now();
                }
                if (in_array($newStatus, ['accepted', 'rejected'], true) && empty($quote->responded_at)) {
                    $update['responded_at'] = now();
                }
            }
            if (array_key_exists('valid_until', $arguments)) {
                $update['valid_until'] = $arguments['valid_until'] !== null && $arguments['valid_until'] !== ''
                    ? $arguments['valid_until']
                    : null;
            }
            if (array_key_exists('response_note', $arguments)) {
                $update['response_note'] = $arguments['response_note'] !== '' ? $arguments['response_note'] : null;
            }
            if (array_key_exists('attach_floor_plans', $arguments)) {
                $v = $arguments['attach_floor_plans'];
                $update['attach_floor_plans'] = ($v === null || $v === '') ? null : (bool) $v;
            }

            if (array_key_exists('is_current', $arguments) && (bool) $arguments['is_current']) {
                // Andere Quotes des Events auf is_current=false setzen
                \Platform\Events\Models\Quote::where('event_id', $quote->event_id)
                    ->where('id', '!=', $quote->id)
                    ->update(['is_current' => false]);
                $update['is_current'] = true;
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren uebergeben.');
            }

            $quote->update($update);

            if (isset($update['status']) && $update['status'] !== $oldStatus) {
                $event = $quote->event;
                if ($event) {
                    ActivityLogger::log($event, 'quote', "Angebot v{$quote->version}: Status {$oldStatus} -> {$update['status']} via Tool");
                }
            }

            return ToolResult::success([
                'quote' => [
                    'id'                 => $quote->id,
                    'uuid'               => $quote->uuid,
                    'version'            => $quote->version,
                    'status'             => $quote->status,
                    'is_current'         => (bool) $quote->is_current,
                    'valid_until'        => $quote->valid_until?->toDateString(),
                    'attach_floor_plans' => $quote->attach_floor_plans,
                    'sent_at'            => $quote->sent_at?->toIso8601String(),
                    'responded_at'       => $quote->responded_at?->toIso8601String(),
                ],
                'message' => "Angebot v{$quote->version} aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'quote', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
