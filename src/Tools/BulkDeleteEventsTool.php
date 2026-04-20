<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Delete: mehrere Events loeschen (Soft Delete).
 */
class BulkDeleteEventsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.events.bulk.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /events/bulk - Body: {"event_ids":[]} ODER {"uuids":[]} ODER {"numbers":[]}. Mindestens eines erforderlich. atomic=true (default).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic'    => ['type' => 'boolean'],
                'event_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'uuids'     => ['type' => 'array', 'items' => ['type' => 'string']],
                'numbers'   => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $targets = [];
            foreach (($arguments['event_ids'] ?? []) as $id) {
                $targets[] = ['event_id' => (int) $id];
            }
            foreach (($arguments['uuids'] ?? []) as $uuid) {
                $targets[] = ['uuid' => (string) $uuid];
            }
            foreach (($arguments['numbers'] ?? []) as $num) {
                $targets[] = ['event_number' => (string) $num];
            }

            if (empty($targets)) {
                return ToolResult::error('INVALID_ARGUMENT', 'event_ids, uuids oder numbers ist erforderlich.');
            }

            $atomic = (bool) ($arguments['atomic'] ?? true);
            $singleTool = new DeleteEventTool();

            $run = function () use ($targets, $singleTool, $context, $atomic) {
                $results = []; $ok = 0; $fail = 0;
                foreach ($targets as $idx => $payload) {
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
                                'message' => "Loeschen an Index {$idx}: {$res->error}",
                                'failed_index' => $idx, 'error_code' => $res->errorCode,
                                'error_message' => $res->error, 'results' => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }
                return [
                    'results' => $results,
                    'summary' => ['requested' => count($targets), 'ok' => $ok, 'failed' => $fail],
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Delete der Events: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'bulk',
            'tags'          => ['events', 'event', 'bulk', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'high',
            'idempotent'    => true,
            'side_effects'  => ['deletes'],
        ];
    }
}
