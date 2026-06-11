<?php

namespace Platform\Events\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Platform\Events\Models\Event;
use Platform\Events\Models\Invoice;
use Platform\Events\Models\InvoiceItem;
use Platform\Events\Models\QuoteItem;

/**
 * Erzeugt Rechnungen aus dem aktuellen Angebotsstand eines Events —
 * das Pendant zu QuoteOrderConverter (Quote -> Order) fuer die Strecke
 * Quote -> Invoice.
 *
 * Quelle sind die QuoteItems/QuotePositions ueber alle EventDays (dort
 * haengt der Angebotsstand; Quote-Dokumente sind Versionen/Snapshots).
 * Text-Bausteine (Positions-Gruppen ohne Preis) werden uebersprungen —
 * eine Rechnung enthaelt nur abrechenbare Posten.
 */
class QuoteInvoiceConverter
{
    /**
     * Komplett-Pfad: legt eine Draft-Rechnung an und uebernimmt alle
     * abrechenbaren Angebots-Positionen. Fuer Livewire UND Tool.
     *
     * @return array{invoice: Invoice, items: int, skipped_bausteine: int}
     */
    public static function createFromEvent(Event $event, string $type = 'rechnung'): array
    {
        $invoice = Invoice::create([
            'team_id'          => $event->team_id,
            'user_id'          => Auth::id(),
            'event_id'         => $event->id,
            'invoice_number'   => self::nextInvoiceNumber((int) $event->team_id),
            'type'             => $type,
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
            'created_by'       => Auth::user()?->name,
        ]);

        $result = self::fillFromEvent($invoice, $event);

        return array_merge(['invoice' => $invoice->fresh()], $result);
    }

    /**
     * Uebernimmt alle abrechenbaren QuotePositions des Events als
     * InvoiceItems in die gegebene Rechnung (additiv, an bestehende Items
     * angehaengt) und stoesst recalculate() an.
     *
     * @return array{items: int, skipped_bausteine: int}
     */
    public static function fillFromEvent(Invoice $invoice, Event $event): array
    {
        $dayIds = $event->days()->orderBy('sort_order')->pluck('id');

        $quoteItems = QuoteItem::whereIn('event_day_id', $dayIds)
            ->with('posList')
            ->get()
            // Reihenfolge: Tag (wie days-Sortierung), dann Vorgang
            ->sortBy([
                fn ($a, $b) => $dayIds->search($a->event_day_id) <=> $dayIds->search($b->event_day_id),
                fn ($a, $b) => $a->sort_order <=> $b->sort_order,
            ])
            ->values();

        $bausteinNames = collect(SettingsService::bausteine($event->team_id))
            ->map(fn ($b) => mb_strtolower(trim((string) ($b['name'] ?? ''))))
            ->filter()
            ->all();
        $isBaustein = fn ($gruppe) => in_array(mb_strtolower(trim((string) $gruppe)), $bausteinNames, true);

        $maxSort = (int) InvoiceItem::where('invoice_id', $invoice->id)->max('sort_order');
        $created = 0;
        $skipped = 0;

        foreach ($quoteItems as $quoteItem) {
            foreach ($quoteItem->posList as $pos) {
                if ($isBaustein($pos->gruppe)) {
                    $skipped++;
                    continue;
                }

                $maxSort++;
                InvoiceItem::create([
                    'team_id'     => $invoice->team_id,
                    'user_id'     => Auth::id(),
                    'invoice_id'  => $invoice->id,
                    'gruppe'      => (string) ($pos->gruppe ?? ''),
                    'name'        => (string) ($pos->name ?? ''),
                    'description' => (string) ($pos->inhalt ?? ''),
                    'quantity'    => (float) ($pos->anz ?? 1),
                    'quantity2'   => (float) ($pos->anz2 ?? 0),
                    'gebinde'     => (string) ($pos->gebinde ?? ''),
                    'unit_price'  => (float) ($pos->preis ?? 0),
                    'mwst_rate'   => self::mwstRate($pos->mwst),
                    'total'       => (float) ($pos->gesamt ?? 0),
                    'sort_order'  => $maxSort,
                ]);
                $created++;
            }
        }

        $invoice->recalculate();

        return ['items' => $created, 'skipped_bausteine' => $skipped];
    }

    /**
     * Naechste freie Rechnungsnummer (RE-<Jahr>-NNNN) — gleiche Logik wie
     * der manuelle Anlege-Pfad im Invoices-Tab.
     */
    public static function nextInvoiceNumber(int $teamId, string $prefixBase = 'RE'): string
    {
        $prefix = $prefixBase . '-' . now()->year . '-';
        $last = Invoice::withTrashed()
            ->where('team_id', $teamId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(invoice_number) DESC, invoice_number DESC')
            ->value('invoice_number');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * QuotePosition-MwSt ("0%" | "7%" | "19%") -> InvoiceItem-Rate (int).
     */
    protected static function mwstRate(?string $mwst): int
    {
        $digits = (int) preg_replace('/\D/', '', (string) $mwst);

        return in_array($digits, [0, 7, 19], true) ? $digits : 19;
    }
}
