<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\Contract;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class ListContractsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.contracts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/contracts - Listet Verträge eines Events (Nutzungsvertrag/Optionsbestätigung).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            ['properties' => array_merge($this->eventSelectorSchema(), [
                'type'   => ['type' => 'string', 'description' => 'nutzungsvertrag | optionsbestaetigung'],
                'status' => ['type' => 'string', 'description' => 'draft | sent | signed | rejected'],
            ])]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) return $event;

            $query = Contract::where('event_id', $event->id);
            foreach (['type', 'status'] as $k) {
                if (!empty($arguments[$k])) $query->where($k, $arguments[$k]);
            }
            $this->applyStandardFilters($query, $arguments, ['type', 'status', 'version', 'is_current']);
            $this->applyStandardSort($query, $arguments, ['version', 'created_at'], 'version', 'desc');
            $this->applyStandardPagination($query, $arguments);

            $contracts = $query->get()->map(fn (Contract $c) => [
                'id' => $c->id, 'uuid' => $c->uuid, 'type' => $c->type, 'status' => $c->status,
                'version' => $c->version, 'is_current' => (bool) $c->is_current,
                'token' => $c->token, 'sent_at' => $c->sent_at,
                'signed_at' => $c->signed_at, 'created_at' => $c->created_at,
            ])->toArray();

            return ToolResult::success([
                'contracts' => $contracts, 'count' => count($contracts), 'event_id' => $event->id,
                'message' => count($contracts) . ' Vertrag/Verträge.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category' => 'query', 'tags' => ['events', 'contract', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true];
    }
}
