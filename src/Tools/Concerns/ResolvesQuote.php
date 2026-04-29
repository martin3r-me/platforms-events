<?php

namespace Platform\Events\Tools\Concerns;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Quote;

/**
 * Helfer, um ein Quote anhand quote_id|quote_uuid|quote_token zu finden
 * und die Team-Zugriffsberechtigung zu pruefen.
 */
trait ResolvesQuote
{
    /**
     * @return Quote|ToolResult
     */
    protected function resolveQuote(array $arguments, ToolContext $context): Quote|ToolResult
    {
        if (!$context->user) {
            return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
        }

        $query = Quote::query();
        if (!empty($arguments['quote_id'])) {
            $query->where('id', (int) $arguments['quote_id']);
        } elseif (!empty($arguments['quote_uuid'])) {
            $query->where('uuid', $arguments['quote_uuid']);
        } elseif (!empty($arguments['quote_token'])) {
            $query->where('token', $arguments['quote_token']);
        } else {
            return ToolResult::error('VALIDATION_ERROR', 'quote_id, quote_uuid oder quote_token ist erforderlich.');
        }

        $quote = $query->first();
        if (!$quote) {
            return ToolResult::error('QUOTE_NOT_FOUND', 'Das Angebot wurde nicht gefunden.');
        }

        $hasAccess = $context->user->teams()->where('teams.id', $quote->team_id)->exists();
        if (!$hasAccess) {
            return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Angebot.');
        }

        return $quote;
    }

    protected function quoteSelectorSchema(): array
    {
        return [
            'quote_id'    => ['type' => 'integer', 'description' => 'ID des Angebots.'],
            'quote_uuid'  => ['type' => 'string',  'description' => 'UUID des Angebots.'],
            'quote_token' => ['type' => 'string',  'description' => 'Public-Token (48 Zeichen).'],
        ];
    }
}
