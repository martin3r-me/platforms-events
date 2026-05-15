<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\Quote;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Legt ein neues Angebot (Quote) am Event an. Wenn bereits ein
 * is_current-Quote existiert, wird der bestehende NICHT geaendert; das neue
 * wird zusaetzlich angelegt (kein automatischer Versions-Wechsel).
 */
class CreateQuoteTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.quotes.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/quotes - Legt ein neues Angebot fuer ein Event an. '
            . 'Pflicht: event_selector (event_id|event_uuid|event_number). '
            . 'Optional: status (draft|sent|accepted|rejected, default draft), valid_until (YYYY-MM-DD), '
            . 'attach_floor_plans (boolean, null = Team-Default), set_current (boolean, default false – '
            . 'macht alle bestehenden Quotes der gleichen Stamm-Linie auf is_current=false und das neue auf true).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'status'             => ['type' => 'string', 'description' => 'draft|sent|accepted|rejected (default draft).'],
                'valid_until'        => ['type' => 'string', 'description' => 'YYYY-MM-DD.'],
                'attach_floor_plans' => ['type' => ['boolean', 'null'], 'description' => 'true/false setzt Override; null = Team-Default. Default: null.'],
                'set_current'        => ['type' => 'boolean', 'description' => 'Wenn true, wird das neue Angebot zum aktuellen (bestehende auf false). Default: true.'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) {
                return $event;
            }

            $setCurrent = (bool) ($arguments['set_current'] ?? true);
            $version = (int) (Quote::where('event_id', $event->id)->max('version') ?? 0) + 1;

            if ($setCurrent) {
                Quote::where('event_id', $event->id)->update(['is_current' => false]);
            }

            $data = [
                'team_id'    => $event->team_id,
                'user_id'    => Auth::id(),
                'event_id'   => $event->id,
                'status'     => $arguments['status'] ?? 'draft',
                'version'    => $version,
                'is_current' => $setCurrent,
            ];
            if (array_key_exists('valid_until', $arguments) && $arguments['valid_until'] !== null && $arguments['valid_until'] !== '') {
                $data['valid_until'] = $arguments['valid_until'];
            } else {
                // Default-Gueltigkeit aus Team-Settings, wenn nicht explizit gesetzt.
                $data['valid_until'] = now()->addDays(
                    \Platform\Events\Services\SettingsService::quoteDefaultValidityDays($event->team_id)
                )->toDateString();
            }
            if (array_key_exists('attach_floor_plans', $arguments)) {
                $v = $arguments['attach_floor_plans'];
                $data['attach_floor_plans'] = $v === null || $v === '' ? null : (bool) $v;
            }

            $quote = Quote::create($data);

            ActivityLogger::log($event, 'quote', "Angebot v{$quote->version} via Tool angelegt");

            return ToolResult::success([
                'quote' => [
                    'id'          => $quote->id,
                    'uuid'        => $quote->uuid,
                    'token'       => $quote->token,
                    'version'     => $quote->version,
                    'status'      => $quote->status,
                    'is_current'  => (bool) $quote->is_current,
                    'event_id'    => $quote->event_id,
                    'valid_until' => $quote->valid_until?->toDateString(),
                    'attach_floor_plans' => $quote->attach_floor_plans,
                ],
                'message' => "Angebot v{$quote->version} fuer Event {$event->event_number} angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'quote', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
