<?php

namespace Platform\Events\Tools;

use Illuminate\Support\Facades\Auth;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;

/**
 * Legt eine neue Angebots-Position (Artikelzeile) an einem QuoteItem an.
 * Unterstützt auch Text-Zeilen (gruppe = Headline / Speisentexte / Trenntext).
 */
class CreateQuotePositionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'events.quote-positions.CREATE';
    }

    public function getDescription(): string
    {
        return 'POST /events/quote-items/{id}/positions - Legt eine Angebots-Position an. '
            . 'Identifikation via quote_item_id oder quote_item_uuid. '
            . 'Gesamt wird automatisch aus anz × preis berechnet wenn nicht angegeben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'quote_item_id'   => ['type' => 'integer', 'description' => 'ID des QuoteItems.'],
                'quote_item_uuid' => ['type' => 'string',  'description' => 'UUID des QuoteItems.'],
                'gruppe'          => ['type' => 'string',  'description' => 'Gruppe / Typ. "Headline"/"Speisentexte"/"Trenntext" erzeugt Text-Zeilen.'],
                'name'            => ['type' => 'string',  'description' => 'Bezeichnung der Position.'],
                'anz'             => ['type' => 'string',  'description' => 'Anzahl (als String, z.B. "10" oder "1,5").'],
                'anz2'            => ['type' => 'string',  'description' => 'Optional: zweite Mengenangabe.'],
                'uhrzeit'         => ['type' => 'string',  'description' => 'Optional: Von-Zeit.'],
                'bis'             => ['type' => 'string',  'description' => 'Optional: Bis-Zeit.'],
                'gebinde'         => ['type' => 'string',  'description' => 'Optional: Gebinde, z.B. "1 Port.".'],
                'ek'              => ['type' => 'number',  'description' => 'Optional: EK-Preis.'],
                'preis'           => ['type' => 'number',  'description' => 'VK-Preis pro Einheit.'],
                'mwst'            => ['type' => 'string',  'description' => 'Optional: MwSt-Satz "0%"/"7%"/"19%" (default "7%").'],
                'gesamt'          => ['type' => 'number',  'description' => 'Optional: Gesamt-Betrag. Leer = anz × preis.'],
                'bemerkung'       => ['type' => 'string',  'description' => 'Optional: Bemerkung.'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $quoteItem = null;
            if (!empty($arguments['quote_item_id'])) {
                $quoteItem = QuoteItem::find($arguments['quote_item_id']);
            } elseif (!empty($arguments['quote_item_uuid'])) {
                $quoteItem = QuoteItem::where('uuid', $arguments['quote_item_uuid'])->first();
            }
            if (!$quoteItem) {
                return ToolResult::error('VALIDATION_ERROR', 'quote_item_id oder quote_item_uuid ist erforderlich.');
            }

            $event = $quoteItem->eventDay?->event;
            if (!$event || !$context->user->teams()->where('teams.id', $event->team_id)->exists()) {
                return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff auf das Event.');
            }

            $anz   = (float) ($arguments['anz'] ?? 0);
            $preis = (float) ($arguments['preis'] ?? 0);
            $gesamt = isset($arguments['gesamt']) && $arguments['gesamt'] !== ''
                ? (float) $arguments['gesamt']
                : $anz * $preis;

            $maxSort = (int) QuotePosition::where('quote_item_id', $quoteItem->id)->max('sort_order');

            $position = QuotePosition::create([
                'team_id'       => $event->team_id,
                'user_id'       => Auth::id(),
                'quote_item_id' => $quoteItem->id,
                'gruppe'        => (string) ($arguments['gruppe']    ?? ''),
                'name'          => (string) ($arguments['name']      ?? ''),
                'anz'           => (string) ($arguments['anz']       ?? ''),
                'anz2'          => (string) ($arguments['anz2']      ?? ''),
                'uhrzeit'       => (string) ($arguments['uhrzeit']   ?? ''),
                'bis'           => (string) ($arguments['bis']       ?? ''),
                'gebinde'       => (string) ($arguments['gebinde']   ?? ''),
                'ek'            => (float)  ($arguments['ek']        ?? 0),
                'preis'         => $preis,
                'mwst'          => (string) ($arguments['mwst']      ?? '7%'),
                'gesamt'        => $gesamt,
                'bemerkung'     => (string) ($arguments['bemerkung'] ?? ''),
                'sort_order'    => $maxSort + 1,
            ]);

            // Vorgang-Summen refresh
            $positions = $quoteItem->posList()->get();
            $quoteItem->update([
                'artikel'    => $positions->count(),
                'positionen' => $positions->count(),
                'umsatz'     => (float) $positions->sum('gesamt'),
            ]);

            return ToolResult::success([
                'position' => [
                    'id' => $position->id, 'uuid' => $position->uuid,
                    'gruppe' => $position->gruppe, 'name' => $position->name,
                    'anz' => $position->anz, 'preis' => (float) $position->preis,
                    'gesamt' => (float) $position->gesamt,
                ],
                'message' => "Position «{$position->name}» hinzugefügt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'mutation', 'tags' => ['events', 'quote', 'position', 'create'],
            'read_only' => false, 'requires_auth' => true, 'requires_team' => true,
            'risk_level' => 'moderate', 'idempotent' => false,
        ];
    }
}
