<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Quote;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Tools\Concerns\ResolvesQuote;

/**
 * Loescht ein Angebot (Soft-Delete). Wenn das geloeschte Angebot is_current
 * war, wird die naechste hoechste Version derselben Stamm-Linie als current
 * markiert.
 */
class DeleteQuoteTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuote;

    public function getName(): string
    {
        return 'events.quotes.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/quotes/{id} - Loescht ein Angebot (soft delete). '
            . 'Identifikation: quote_id|quote_uuid|quote_token. Wenn das Angebot is_current war, '
            . 'wird die naechste verfuegbare Version desselben Events als current markiert.';
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

            $event   = $quote->event;
            $eventId = $quote->event_id;
            $version = $quote->version;
            $wasCurrent = (bool) $quote->is_current;

            $quote->delete();

            if ($wasCurrent) {
                $next = Quote::where('event_id', $eventId)
                    ->latest('version')
                    ->first();
                if ($next) {
                    $next->update(['is_current' => true]);
                }
            }

            if ($event) {
                ActivityLogger::log($event, 'quote', "Angebot v{$version} via Tool geloescht");
            }

            return ToolResult::success([
                'event_id' => $eventId,
                'version'  => $version,
                'message'  => "Angebot v{$version} geloescht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'quote', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'destructive',
            'idempotent'    => false,
            'side_effects'  => ['deletes'],
        ];
    }
}
