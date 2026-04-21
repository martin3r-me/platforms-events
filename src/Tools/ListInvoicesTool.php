<?php

namespace Platform\Events\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Events\Models\Invoice;
use Platform\Events\Tools\Concerns\ResolvesEvent;

class ListInvoicesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEvent;

    public function getName(): string
    {
        return 'events.invoices.GET';
    }

    public function getDescription(): string
    {
        return 'GET /events/{event}/invoices - Listet Rechnungen eines Events.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            ['properties' => array_merge($this->eventSelectorSchema(), [
                'type'   => ['type' => 'string', 'description' => 'rechnung | teilrechnung | schlussrechnung | gutschrift | storno'],
                'status' => ['type' => 'string', 'description' => 'draft | sent | paid | overdue | cancelled'],
            ])]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $event = $this->resolveEvent($arguments, $context);
            if ($event instanceof ToolResult) return $event;

            $query = Invoice::where('event_id', $event->id);
            foreach (['type', 'status'] as $k) {
                if (!empty($arguments[$k])) $query->where($k, $arguments[$k]);
            }
            $this->applyStandardFilters($query, $arguments, ['type', 'status', 'invoice_number']);
            $this->applyStandardSort($query, $arguments, ['invoice_date', 'invoice_number', 'created_at'], 'invoice_date', 'desc');
            $this->applyStandardPagination($query, $arguments);

            $invoices = $query->get()->map(fn (Invoice $i) => [
                'id' => $i->id, 'uuid' => $i->uuid,
                'invoice_number' => $i->invoice_number, 'type' => $i->type, 'status' => $i->status,
                'customer_company' => $i->customer_company,
                'invoice_date' => $i->invoice_date?->format('Y-m-d'),
                'due_date' => $i->due_date?->format('Y-m-d'),
                'netto' => (float) $i->netto, 'brutto' => (float) $i->brutto,
                'mwst_7' => (float) $i->mwst_7, 'mwst_19' => (float) $i->mwst_19,
                'reminder_level' => $i->reminder_level,
            ])->toArray();

            return ToolResult::success([
                'invoices' => $invoices, 'count' => count($invoices), 'event_id' => $event->id,
                'message' => count($invoices) . ' Rechnung(en).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category' => 'query', 'tags' => ['events', 'invoice', 'list'],
            'read_only' => true, 'requires_auth' => true, 'requires_team' => false,
            'risk_level' => 'safe', 'idempotent' => true];
    }
}
