<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Update: mehrere Events aktualisieren.
 *
 * Zwei Modi:
 *  (1) event_ids (oder uuids) + data  → gleiche Änderung für alle
 *  (2) updates[] mit individuellen Aenderungen pro Event
 */
class BulkUpdateEventsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.events.bulk.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /events/bulk - Zwei Modi: '
            . '(1) {"event_ids":[1,2,3],"data":{"status":"Abgeschlossen"}} fuer gemeinsame Aenderung. '
            . '(2) {"updates":[{"event_id":1,"name":"..."}, {"uuid":"...","status":"..."}]} fuer individuelle Aenderungen. '
            . 'Identifikation je Update: event_id ODER uuid ODER event_number.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic'     => ['type' => 'boolean'],
                'event_ids'  => ['type' => 'array', 'items' => ['type' => 'integer']],
                'uuids'      => ['type' => 'array', 'items' => ['type' => 'string']],
                'numbers'    => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Liste von event_number (Modus 1).'],
                'data'       => ['type' => 'object'],
                'updates'    => ['type' => 'array', 'items' => ['type' => 'object']],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $updates = $this->buildUpdateList($arguments);
            if ($updates instanceof ToolResult) {
                return $updates;
            }
            if (empty($updates)) {
                return ToolResult::error('INVALID_ARGUMENT', 'Keine Updates zum Ausfuehren gefunden.');
            }

            $atomic = (bool) ($arguments['atomic'] ?? true);
            $singleTool = new UpdateEventTool();

            $run = function () use ($updates, $singleTool, $context, $atomic) {
                $results = []; $ok = 0; $fail = 0;

                foreach ($updates as $idx => $payload) {
                    $res = $singleTool->execute($payload, $context);
                    if ($res->success) {
                        $ok++;
                        $results[] = ['index' => $idx, 'ok' => true, 'data' => $res->data];
                    } else {
                        $fail++;
                        $results[] = ['index' => $idx, 'ok' => false, 'error' => ['code' => $res->errorCode, 'message' => $res->error]];
                        if ($atomic) {
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Update an Index {$idx}: {$res->error}",
                                'failed_index' => $idx, 'error_code' => $res->errorCode,
                                'error_message' => $res->error, 'results' => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }

                return [
                    'results' => $results,
                    'summary' => ['requested' => count($updates), 'ok' => $ok, 'failed' => $fail],
                ];
            };

            if ($atomic) {
                try {
                    $payload = DB::transaction(fn () => $run());
                } catch (\RuntimeException $e) {
                    $data = json_decode($e->getMessage(), true);
                    if (is_array($data) && isset($data['code'])) {
                        return ToolResult::error($data['code'], $data['message']);
                    }
                    throw $e;
                }
            } else {
                $payload = $run();
            }

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Update der Events: ' . $e->getMessage());
        }
    }

    /** @return array<int,array<string,mixed>>|ToolResult */
    protected function buildUpdateList(array $arguments): array|ToolResult
    {
        $hasSelectors = !empty($arguments['event_ids']) || !empty($arguments['uuids']) || !empty($arguments['numbers']);
        $hasMode1 = $hasSelectors && !empty($arguments['data']);
        $hasMode2 = !empty($arguments['updates']);

        if ($hasMode1 && $hasMode2) {
            return ToolResult::error('INVALID_ARGUMENT', 'Entweder event_ids/uuids/numbers+data ODER updates[] – nicht beides.');
        }
        if (!$hasMode1 && !$hasMode2) {
            return ToolResult::error('INVALID_ARGUMENT', 'Entweder event_ids/uuids/numbers+data ODER updates[] angeben.');
        }

        if ($hasMode1) {
            $data = $arguments['data'];
            if (!is_array($data) || empty($data)) {
                return ToolResult::error('INVALID_ARGUMENT', 'data muss ein nicht-leeres Objekt sein.');
            }
            $list = [];
            foreach (($arguments['event_ids'] ?? []) as $id) {
                $list[] = array_merge(['event_id' => (int) $id], $data);
            }
            foreach (($arguments['uuids'] ?? []) as $uuid) {
                $list[] = array_merge(['uuid' => (string) $uuid], $data);
            }
            foreach (($arguments['numbers'] ?? []) as $num) {
                $list[] = array_merge(['event_number' => (string) $num], $data);
            }
            return $list;
        }

        $list = [];
        foreach ($arguments['updates'] as $u) {
            if (!is_array($u)) {
                continue;
            }
            if (empty($u['event_id']) && empty($u['uuid']) && empty($u['event_number'])) {
                return ToolResult::error('INVALID_ARGUMENT', 'Jedes Update benoetigt event_id, uuid oder event_number.');
            }
            $list[] = $u;
        }
        return $list;
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'bulk',
            'tags'          => ['events', 'event', 'bulk', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'medium',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
