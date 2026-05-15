<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Quote;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Tools\Concerns\ResolvesQuote;

/**
 * Legt eine neue Version eines Angebots an. Spiegelt newVersion()-Logik aus
 * Livewire\Detail\Quotes: alle Quotes der gleichen Stamm-Linie (parent_id =
 * Root) werden auf is_current=false gesetzt, das neue Quote bekommt
 * version = max+1, parent_id = Root, is_current=true.
 */
class CreateQuoteVersionTool implements ToolContract, ToolMetadataContract
{
    use ResolvesQuote;

    public function getName(): string
    {
        return 'events.quotes.NEW_VERSION';
    }

    public function getDescription(): string
    {
        return 'POST /events/quotes/{id}/version - Legt eine neue Version eines Angebots an. '
            . 'Identifikation: quote_id|quote_uuid|quote_token (das Quelle-Quote definiert die Stamm-Linie). '
            . 'Bestehende Versionen der Linie werden auf is_current=false gesetzt; neues bekommt version=max+1.';
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
            $current = $this->resolveQuote($arguments, $context);
            if ($current instanceof ToolResult) {
                return $current;
            }
            $event = $current->event;
            if (!$event) {
                return ToolResult::error('VALIDATION_ERROR', 'Quote ohne Event – kann keine neue Version anlegen.');
            }

            $rootId = $current->getRootParentId();

            $maxVersion = (int) Quote::where('event_id', $event->id)
                ->where(function ($q) use ($rootId) {
                    $q->where('id', $rootId)->orWhere('parent_id', $rootId);
                })
                ->max('version');

            Quote::where('event_id', $event->id)
                ->where(function ($q) use ($rootId) {
                    $q->where('id', $rootId)->orWhere('parent_id', $rootId);
                })
                ->update(['is_current' => false]);

            $new = Quote::create([
                'team_id'    => $event->team_id,
                'user_id'    => Auth::id() ?: $context->user?->id,
                'event_id'   => $event->id,
                'status'     => 'draft',
                'version'    => $maxVersion + 1,
                'parent_id'  => $rootId,
                'is_current' => true,
                'valid_until' => now()->addDays(
                    \Platform\Events\Services\SettingsService::quoteDefaultValidityDays($event->team_id)
                )->toDateString(),
            ]);

            ActivityLogger::log($event, 'quote', "Angebot v{$new->version} via Tool angelegt");

            return ToolResult::success([
                'quote' => [
                    'id'         => $new->id,
                    'uuid'       => $new->uuid,
                    'token'      => $new->token,
                    'version'    => $new->version,
                    'parent_id'  => $new->parent_id,
                    'status'     => $new->status,
                    'is_current' => true,
                    'event_id'   => $new->event_id,
                ],
                'message' => "Neue Version v{$new->version} angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action', 'tags' => ['events', 'quote', 'version'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'write', 'idempotent' => false, 'side_effects' => ['creates', 'updates'],
        ];
    }
}
