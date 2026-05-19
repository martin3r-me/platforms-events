<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Tools\Concerns\NormalizesMwst;

class CreateOrderPositionTool implements ToolContract, ToolMetadataContract
{
    use NormalizesMwst;

    public function getName(): string
    {
        return 'events.order-positions.CREATE';
    }

    public function getDescription(): string
    {
        return 'POST /events/order-items/{id}/positions - Legt eine Bestell-Position an. '
            . 'Gesamt = anz × ek wenn nicht angegeben. '
            . 'Text-Zeilen (ohne Preis) entstehen, wenn `gruppe` einem Text-Baustein des Teams entspricht — '
            . 'verfuegbare Bausteine liefert events.settings.bausteine.GET.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_item_id'    => ['type' => 'integer'],
                'order_item_uuid'  => ['type' => 'string'],
                'gruppe'           => ['type' => 'string', 'description' => 'Gruppe / Typ. Entspricht der Wert dem `name` eines Text-Bausteins (events.settings.bausteine.GET), wird die Position als Text-Zeile ohne Preis behandelt.'],
                'name'             => ['type' => 'string'],
                'anz'              => ['type' => 'string'],
                'anz2'             => ['type' => 'string'],
                'start_time'          => ['type' => 'string'],
                'end_time'              => ['type' => 'string'],
                'gebinde'          => ['type' => 'string'],
                'ek'               => ['type' => 'number'],
                'mwst'             => ['type' => 'string'],
                'gesamt'           => ['type' => 'number'],
                'bemerkung'        => ['type' => 'string'],
                'procurement_type' => ['type' => 'string', 'description' => 'Optional: Beschaffungs-Typ (Lager / Einkauf / Eigenproduktion ...).'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) return ToolResult::error('AUTH_ERROR', 'Kein User.');

            $orderItem = null;
            if (!empty($arguments['order_item_id'])) {
                $orderItem = OrderItem::find($arguments['order_item_id']);
            } elseif (!empty($arguments['order_item_uuid'])) {
                $orderItem = OrderItem::where('uuid', $arguments['order_item_uuid'])->first();
            }
            if (!$orderItem) return ToolResult::error('VALIDATION_ERROR', 'order_item_id/uuid ist erforderlich.');

            $event = $orderItem->eventDay?->event;
            if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');
            }

            // MwSt-Numeric-Alias (Excel/DATEV: 1→19%, 3→7%, 0→0%).
            $aliasesApplied = [];
            if ($mwstAlias = $this->normalizeMwstField($arguments, 'mwst')) {
                $aliasesApplied[] = $mwstAlias;
            }

            $anz    = (float) ($arguments['anz'] ?? 0);
            $ek     = (float) ($arguments['ek']  ?? 0);
            $gesamt = isset($arguments['gesamt']) && $arguments['gesamt'] !== ''
                ? (float) $arguments['gesamt']
                : $anz * $ek;

            $maxSort = (int) OrderPosition::where('order_item_id', $orderItem->id)->max('sort_order');

            $procurementType = isset($arguments['procurement_type']) && trim((string) $arguments['procurement_type']) !== ''
                ? trim((string) $arguments['procurement_type'])
                : null;

            $pos = OrderPosition::create([
                'team_id'          => $event->team_id,
                'user_id'          => Auth::id(),
                'order_item_id'    => $orderItem->id,
                'gruppe'           => (string) ($arguments['gruppe']    ?? ''),
                'name'             => (string) ($arguments['name']      ?? ''),
                'anz'              => (string) ($arguments['anz']       ?? ''),
                'anz2'             => (string) ($arguments['anz2']      ?? ''),
                'start_time'          => (string) ($arguments['start_time']   ?? ''),
                'end_time'              => (string) ($arguments['end_time']       ?? ''),
                'gebinde'          => (string) ($arguments['gebinde']   ?? ''),
                'ek'               => $ek,
                'mwst'             => (string) ($arguments['mwst']      ?? '7%'),
                'gesamt'           => $gesamt,
                'bemerkung'        => (string) ($arguments['bemerkung'] ?? ''),
                'procurement_type' => $procurementType,
                'sort_order'       => $maxSort + 1,
            ]);

            $positions = $orderItem->posList()->get();
            $orderItem->update([
                'artikel'    => $positions->count(),
                'positionen' => $positions->count(),
                'einkauf'    => (float) $positions->sum('gesamt'),
            ]);

            return ToolResult::success([
                'position' => ['id' => $pos->id, 'uuid' => $pos->uuid, 'name' => $pos->name, 'gesamt' => (float) $pos->gesamt],
                'aliases_applied' => $aliasesApplied,
                'message' => "Bestell-Position «{$pos->name}» hinzugefügt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category' => 'mutation', 'tags' => ['events', 'order', 'position', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'moderate', 'idempotent' => false];
    }
}
