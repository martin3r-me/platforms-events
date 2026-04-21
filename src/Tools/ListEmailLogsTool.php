<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\EmailLog;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class ListEmailLogsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.email-log.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/email-log - Listet E-Mail-Verlauf eines Events.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            ['properties' => array_merge($this->eventSelectorSchema(), [
                'type'   => ['type' => 'string', 'description' => 'Optional: quote | invoice | contract | reminder | custom'],
                'status' => ['type' => 'string', 'description' => 'Optional: sent | opened | failed'],
            ])]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) return $event;

            $query = EmailLog::where('event_id', $event->id);
            foreach (['type', 'status'] as $k) {
                if (!empty($arguments[$k])) $query->where($k, $arguments[$k]);
            }
            $this->applyStandardFilters($query, $arguments, ['type', 'status', 'to']);
            $this->applyStandardSearch($query, $arguments, ['subject', 'to', 'body']);
            $this->applyStandardSort($query, $arguments, ['created_at'], 'created_at', 'desc');
            $this->applyStandardPagination($query, $arguments);

            $emails = $query->get()->map(fn (EmailLog $e) => [
                'id' => $e->id, 'uuid' => $e->uuid,
                'type' => $e->type, 'status' => $e->status,
                'to' => $e->to, 'cc' => $e->cc,
                'subject' => $e->subject,
                'attachment_name' => $e->attachment_name,
                'sent_by' => $e->sent_by,
                'created_at' => $e->created_at?->format('Y-m-d H:i'),
                'opened_at'  => $e->opened_at?->format('Y-m-d H:i'),
            ])->toArray();

            return ToolResult::success([
                'emails' => $emails, 'count' => count($emails), 'event_id' => $event->id,
                'message' => count($emails) . ' E-Mail(s) im Verlauf.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category' => 'query', 'tags' => ['events', 'email', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true];
    }
}
