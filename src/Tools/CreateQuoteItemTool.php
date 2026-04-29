<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\EventDay;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Legt einen neuen Angebots-Vorgang (QuoteItem) an einem Event-Tag an.
 */
class CreateQuoteItemTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.quote-items.CREATE';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/days/{day}/quote-items - Legt einen Angebots-Vorgang (z.B. Speisen) an einem Event-Tag an. '
            . 'Optional beverage_mode als Vorgang-Default (Verbrauch/Alternativ/Auf Anfrage); Positionen koennen ueberschreiben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'event_day_id'   => ['type' => 'integer', 'description' => 'ID des Event-Tages. Alternative: event_day_uuid.'],
                'event_day_uuid' => ['type' => 'string',  'description' => 'UUID des Event-Tages.'],
                'typ'            => ['type' => 'string',  'description' => 'Vorgangs-Typ, z.B. Speisen/Getränke/Personal/Equipment.'],
                'status'         => ['type' => 'string',  'description' => 'Optional: Status (default "Entwurf").'],
                'mwst'           => ['type' => 'string',  'description' => 'Optional: MwSt-Satz "0%", "7%", "19%" (default "19%").'],
                'beverage_mode'  => ['type' => 'string',  'description' => 'Optional: Default-Modus fuer Getraenke-Positionen dieses Vorgangs (z.B. "Verbrauch", "Alternativ", "Auf Anfrage"). Positionen koennen via beverage_mode-Override abweichen. Leer/null = kein Default.'],
            ]),
            'required' => ['typ'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) return $event;

            $day = null;
            if (!empty($arguments['event_day_id'])) {
                $day = EventDay::where('event_id', $event->id)->find($arguments['event_day_id']);
            } elseif (!empty($arguments['event_day_uuid'])) {
                $day = EventDay::where('event_id', $event->id)->where('uuid', $arguments['event_day_uuid'])->first();
            }
            if (!$day) {
                return ToolResult::error('VALIDATION_ERROR', 'Kein gültiger Event-Tag angegeben (event_day_id oder event_day_uuid).');
            }

            $typ = trim((string) ($arguments['typ'] ?? ''));
            if ($typ === '') {
                return ToolResult::error('VALIDATION_ERROR', 'typ ist erforderlich.');
            }

            $beverageMode = isset($arguments['beverage_mode']) && trim((string) $arguments['beverage_mode']) !== ''
                ? trim((string) $arguments['beverage_mode'])
                : null;

            $maxSort = (int) QuoteItem::where('event_day_id', $day->id)->max('sort_order');
            $item = QuoteItem::create([
                'team_id'       => $event->team_id,
                'user_id'       => Auth::id(),
                'event_day_id'  => $day->id,
                'typ'           => $typ,
                'status'        => $arguments['status'] ?? 'Entwurf',
                'mwst'          => $arguments['mwst']   ?? '19%',
                'beverage_mode' => $beverageMode,
                'sort_order'    => $maxSort + 1,
            ]);

            return ToolResult::success([
                'quote_item' => [
                    'id' => $item->id, 'uuid' => $item->uuid, 'typ' => $item->typ,
                    'status' => $item->status, 'mwst' => $item->mwst,
                    'beverage_mode' => $item->beverage_mode,
                    'event_day_id' => $item->event_day_id,
                ],
                'message' => "Vorgang «{$item->typ}» angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'mutation', 'tags' => ['events', 'quote', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'moderate', 'idempotent' => false,
        ];
    }
}
