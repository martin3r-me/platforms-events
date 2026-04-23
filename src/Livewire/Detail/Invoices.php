<?php

namespace Platform\Events\Livewire\Detail;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Events\Models\Event;
use Platform\Events\Models\Invoice;
use Platform\Events\Models\InvoiceItem;
use Platform\Events\Services\ActivityLogger;

class Invoices extends Component
{
    public int $eventId;
    public ?int $activeInvoiceId = null;

    public bool $showCreateModal = false;
    public string $newType = 'rechnung';

    public bool $showItemsModal = false;
    public array $newItem = [
        'name' => '', 'gruppe' => '', 'description' => '',
        'quantity' => 1, 'quantity2' => 0, 'gebinde' => '',
        'unit_price' => 0, 'mwst_rate' => 19, 'total' => 0,
    ];

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->activeInvoiceId = Invoice::where('event_id', $eventId)
            ->where('is_current', true)
            ->latest('id')
            ->value('id');
    }

    protected function event(): Event
    {
        $event = Event::findOrFail($this->eventId);
        $team = Auth::user()->currentTeam;
        if ($event->team_id !== $team?->id) abort(403);
        return $event;
    }

    // ========== Invoice Create ==========

    public function openCreate(): void
    {
        $this->newType = 'rechnung';
        $this->showCreateModal = true;
    }

    public function createInvoice(): void
    {
        $event = $this->event();
        $user = Auth::user();

        $prefix = 'RE-' . now()->year . '-';
        $last = Invoice::withTrashed()
            ->where('team_id', $event->team_id)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(invoice_number) DESC, invoice_number DESC')
            ->value('invoice_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        $number = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'team_id'          => $event->team_id,
            'user_id'          => $user->id,
            'event_id'         => $event->id,
            'invoice_number'   => $number,
            'type'             => $this->newType,
            'status'           => 'draft',
            'customer_company' => $event->invoice_to ?: $event->customer ?: '',
            'customer_contact' => $event->invoice_contact ?: '',
            'invoice_date'     => now(),
            'due_date'         => now()->addDays(14),
            'cost_center'      => $event->cost_center ?: '',
            'cost_carrier'     => $event->cost_carrier ?: '',
            'token'            => Str::random(48),
            'version'          => 1,
            'is_current'       => true,
            'created_by'       => $user->name ?? null,
        ]);

        $this->activeInvoiceId = $invoice->id;
        $this->showCreateModal = false;
        ActivityLogger::log($event, 'invoice', "Rechnung {$invoice->invoice_number} ({$invoice->type}) angelegt");
    }

    public function selectInvoice(int $invoiceId): void
    {
        if (Invoice::where('event_id', $this->eventId)->where('id', $invoiceId)->exists()) {
            $this->activeInvoiceId = $invoiceId;
        }
    }

    public function setStatus(string $status): void
    {
        if (!$this->activeInvoiceId) return;
        $i = Invoice::find($this->activeInvoiceId);
        if ($i && $i->event_id === $this->eventId) {
            $old = $i->status;
            $payload = ['status' => $status];
            if ($status === 'sent')  $payload['sent_at']     = now();
            if ($status === 'paid')  $payload['payment_date'] = now();
            $i->update($payload);
            ActivityLogger::log($this->event(), 'invoice', "Rechnung-Status: „{$old}“ → „{$status}“");
        }
    }

    public function markReminded(): void
    {
        if (!$this->activeInvoiceId) return;
        $i = Invoice::find($this->activeInvoiceId);
        if ($i) {
            $i->update([
                'reminded_at'    => now(),
                'reminder_level' => ($i->reminder_level ?? 0) + 1,
            ]);
            ActivityLogger::log($this->event(), 'invoice', "Rechnung {$i->invoice_number}: Mahnstufe {$i->reminder_level}");
        }
    }

    public function createGutschrift(): void
    {
        if (!$this->activeInvoiceId) return;
        $orig = Invoice::find($this->activeInvoiceId);
        if (!$orig) return;
        $event = $this->event();

        $prefix = 'GS-' . now()->year . '-';
        $last = Invoice::withTrashed()
            ->where('team_id', $event->team_id)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(invoice_number) DESC, invoice_number DESC')
            ->value('invoice_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        $number = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        $gs = $orig->replicate(['token', 'invoice_number']);
        $gs->invoice_number     = $number;
        $gs->type               = 'gutschrift';
        $gs->status             = 'draft';
        $gs->related_invoice_id = $orig->id;
        $gs->netto              = -abs((float) $orig->netto);
        $gs->mwst_7             = -abs((float) $orig->mwst_7);
        $gs->mwst_19            = -abs((float) $orig->mwst_19);
        $gs->brutto             = -abs((float) $orig->brutto);
        $gs->token              = Str::random(48);
        $gs->version            = 1;
        $gs->is_current         = true;
        $gs->save();

        $this->activeInvoiceId = $gs->id;
        ActivityLogger::log($event, 'invoice', "Gutschrift {$gs->invoice_number} zu {$orig->invoice_number}");
    }

    public function deleteInvoice(int $invoiceId): void
    {
        $i = Invoice::where('event_id', $this->eventId)->find($invoiceId);
        if ($i) {
            $i->delete();
            if ($this->activeInvoiceId === $invoiceId) {
                $this->activeInvoiceId = Invoice::where('event_id', $this->eventId)
                    ->latest('id')->value('id');
            }
        }
    }

    // ========== Items ==========

    public function openItems(): void
    {
        if (!$this->activeInvoiceId) return;
        $this->resetNewItem();
        $this->showItemsModal = true;
    }

    public function closeItems(): void
    {
        $this->showItemsModal = false;
    }

    protected function resetNewItem(): void
    {
        $this->newItem = [
            'name' => '', 'gruppe' => '', 'description' => '',
            'quantity' => 1, 'quantity2' => 0, 'gebinde' => '',
            'unit_price' => 0, 'mwst_rate' => 19, 'total' => 0,
        ];
    }

    public function addItem(): void
    {
        if (!$this->activeInvoiceId) return;
        $invoice = Invoice::find($this->activeInvoiceId);
        if (!$invoice) return;

        $maxSort = (int) InvoiceItem::where('invoice_id', $invoice->id)->max('sort_order');

        $total = (float) $this->newItem['total'];
        if ($total <= 0) {
            $total = (float) $this->newItem['quantity'] * (float) $this->newItem['unit_price'];
        }

        InvoiceItem::create([
            'team_id'    => $invoice->team_id,
            'user_id'    => Auth::id(),
            'invoice_id' => $invoice->id,
            'gruppe'     => $this->newItem['gruppe'],
            'name'       => $this->newItem['name'],
            'description'=> $this->newItem['description'],
            'quantity'   => (float) $this->newItem['quantity'],
            'quantity2'  => (float) $this->newItem['quantity2'],
            'gebinde'    => $this->newItem['gebinde'],
            'unit_price' => (float) $this->newItem['unit_price'],
            'mwst_rate'  => (int) $this->newItem['mwst_rate'],
            'total'      => $total,
            'sort_order' => $maxSort + 1,
        ]);

        $invoice->recalculate();
        $this->resetNewItem();
    }

    public function deleteItem(int $itemId): void
    {
        if (!$this->activeInvoiceId) return;
        $item = InvoiceItem::where('invoice_id', $this->activeInvoiceId)->find($itemId);
        if ($item) {
            $item->delete();
            $invoice = Invoice::find($this->activeInvoiceId);
            if ($invoice) $invoice->recalculate();
        }
    }

    public function render()
    {
        $event = Event::findOrFail($this->eventId);
        $invoices = Invoice::where('event_id', $event->id)->orderByDesc('id')->get();
        $active = $this->activeInvoiceId ? Invoice::with('items')->find($this->activeInvoiceId) : null;

        return view('events::livewire.detail.invoices', [
            'event'    => $event,
            'invoices' => $invoices,
            'active'   => $active,
        ]);
    }
}
