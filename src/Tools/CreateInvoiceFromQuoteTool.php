<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Events\Services\ActivityLogger;
use Platform\Events\Services\QuoteInvoiceConverter;
use Platform\Events\Tools\Concerns\ResolvesEvent;

/**
 * Erzeugt eine Draft-Rechnung aus dem aktuellen Angebotsstand eines Events
 * (alle QuotePositions ueber alle EventDays; Text-Bausteine werden
 * uebersprungen). Schliesst die Tool-Kette Angebot -> Auftrag -> Rechnung.
 */
class CreateInvoiceFromQuoteTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.invoices.from-quote.POST';
    }

    public function getDescription(): string
    {
        return 'POST /events/{event}/invoices/from-quote - Erzeugt eine Draft-Rechnung aus dem aktuellen Angebotsstand des Events. Alle abrechenbaren QuotePositions werden als InvoiceItems uebernommen (Text-Bausteine uebersprungen), Summen werden automatisch berechnet (netto/mwst_7/mwst_19/brutto). Optional: type = "rechnung" (Default) | "teilrechnung" | "schlussrechnung". Die Rechnung ist danach via events.invoices.GET sichtbar und im Invoices-Tab editierbar.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge($this->eventSelectorSchema(), [
                'type' => [
                    'type'        => 'string',
                    'description' => 'Rechnungs-Typ: "rechnung" (Default) | "teilrechnung" | "schlussrechnung".',
                ],
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

            $type = (string) ($arguments['type'] ?? 'rechnung');
            if (!in_array($type, ['rechnung', 'teilrechnung', 'schlussrechnung'], true)) {
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    'type "' . $type . '" ist nicht erlaubt. Erlaubt: "rechnung" | "teilrechnung" | "schlussrechnung".'
                );
            }

            $result = QuoteInvoiceConverter::createFromEvent($event, $type);
            $invoice = $result['invoice'];

            if ($result['items'] === 0) {
                // Leere Rechnung ist legitim (User fuellt manuell), aber explizit melden.
                $hint = ' Hinweis: Das Event hat keine abrechenbaren Angebots-Positionen — die Rechnung ist leer.';
            }

            ActivityLogger::log($event, 'invoice', sprintf(
                'Rechnung %s (%s) aus Angebot erzeugt — %d Position(en) uebernommen (via Tool)',
                $invoice->invoice_number,
                $type,
                $result['items'],
            ));

            return ToolResult::success([
                'id'                => $invoice->id,
                'uuid'              => $invoice->uuid,
                'invoice_number'    => $invoice->invoice_number,
                'type'              => $invoice->type,
                'status'            => $invoice->status,
                'items_created'     => $result['items'],
                'skipped_bausteine' => $result['skipped_bausteine'],
                'netto'             => (float) $invoice->netto,
                'mwst_7'            => (float) $invoice->mwst_7,
                'mwst_19'           => (float) $invoice->mwst_19,
                'brutto'            => (float) $invoice->brutto,
                'message'           => "Rechnung {$invoice->invoice_number} ({$type}) aus Angebot erzeugt: "
                    . "{$result['items']} Position(en), brutto " . number_format((float) $invoice->brutto, 2, ',', '.') . ' €.'
                    . ($hint ?? ''),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erzeugen der Rechnung aus dem Angebot: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['events', 'invoice', 'quote', 'convert', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
