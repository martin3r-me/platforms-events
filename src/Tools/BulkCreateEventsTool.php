<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Create: mehrere Events in einem Call anlegen.
 */
class BulkCreateEventsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.events.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/bulk - Body: {events:[{name,...}], team_id?, defaults?}. '
            . 'Jedes Item akzeptiert die gleichen Felder wie events.events.POST. atomic=true (default): alles oder nichts.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic'   => ['type' => 'boolean', 'description' => 'Default true: alles in einer Transaktion.'],
                'team_id'  => ['type' => 'integer', 'description' => 'Optional: Default-Team-ID fuer alle Events.'],
                'defaults' => [
                    'type'        => 'object',
                    'description' => 'Default-Werte, die pro Item ueberschrieben werden koennen.',
                ],
                'events' => [
                    'type'        => 'array',
                    'description' => 'Liste von Events. Pflicht pro Item: name.',
                    'items'       => [
                        'type'     => 'object',
                        'required' => ['name'],
                    ],
                ],
            ],
            'required' => ['events'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $items = $arguments['events'] ?? null;
            if (!is_array($items) || empty($items)) {
                return ToolResult::error('INVALID_ARGUMENT', 'events muss ein nicht-leeres Array sein.');
            }

            $defaults = is_array($arguments['defaults'] ?? null) ? $arguments['defaults'] : [];
            if (isset($arguments['team_id']) && !array_key_exists('team_id', $defaults)) {
                $defaults['team_id'] = $arguments['team_id'];
            }

            $atomic = (bool) ($arguments['atomic'] ?? true);
            $singleTool = new CreateEventTool();

            $run = function () use ($items, $defaults, $singleTool, $context, $atomic) {
                $results   = [];
                $okCount   = 0;
                $failCount = 0;

                foreach ($items as $idx => $item) {
                    if (!is_array($item)) {
                        $failCount++;
                        $results[] = ['index' => $idx, 'ok' => false, 'error' => ['code' => 'INVALID_ITEM', 'message' => 'Item muss Objekt sein.']];
                        if ($atomic) {
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Event an Index {$idx}: Item muss Objekt sein.",
                                'failed_index' => $idx, 'results' => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                        continue;
                    }

                    $payload = array_merge($defaults, $item);
                    $res = $singleTool->execute($payload, $context);
                    if ($res->success) {
                        $okCount++;
                        $results[] = ['index' => $idx, 'ok' => true, 'data' => $res->data];
                    } else {
                        $failCount++;
                        $results[] = ['index' => $idx, 'ok' => false, 'error' => ['code' => $res->errorCode, 'message' => $res->error]];
                        if ($atomic) {
                            $name = $item['name'] ?? '(kein Name)';
                            throw new \RuntimeException(json_encode([
                                'code' => 'BULK_VALIDATION_ERROR',
                                'message' => "Event an Index {$idx} ('{$name}'): {$res->error}",
                                'failed_index' => $idx, 'error_code' => $res->errorCode, 'error_message' => $res->error,
                                'results' => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }

                return [
                    'results' => $results,
                    'summary' => ['requested' => count($items), 'ok' => $okCount, 'failed' => $failCount],
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Create der Events: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'bulk',
            'tags'          => ['events', 'event', 'bulk', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'medium',
            'idempotent'    => false,
        ];
    }
}
