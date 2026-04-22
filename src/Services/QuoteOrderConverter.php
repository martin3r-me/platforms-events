<?php

namespace Platform\Events\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Models\QuoteItem;

/**
 * Konvertiert QuoteItems (Angebots-Vorgaenge) in OrderItems (Bestell-Vorgaenge)
 * und haelt diese synchron. Logik portiert aus dem Alt-Event-Modul:
 *  - convertItem: neuer OrderItem mit Typ-Dedupe ("Speisen (2)" etc.)
 *  - syncItem: existierenden OrderItem via Typ-Matching finden und Positionen
 *    ersetzen
 *  - Bulk-Helpers fuer Tag und Event
 */
class QuoteOrderConverter
{
    /**
     * Erzeugt einen OrderItem samt Positionen aus dem uebergebenen QuoteItem.
     * Bei Typ-Kollision am selben Tag wird ein Suffix "(N)" angehaengt.
     */
    public static function convertItem(QuoteItem $quoteItem): OrderItem
    {
        $quoteItem->loadMissing('posList', 'eventDay');
        $dayId = $quoteItem->event_day_id;
        $teamId = $quoteItem->team_id ?? $quoteItem->eventDay?->team_id;

        // Typ-Dedupe: "Speisen" -> "Speisen (2)" wenn am Tag schon vorhanden
        $baseTyp = (string) $quoteItem->typ;
        $existing = OrderItem::where('event_day_id', $dayId)->pluck('typ')->toArray();
        $typ = $baseTyp;
        $counter = 2;
        while (in_array($typ, $existing, true)) {
            $typ = $baseTyp . ' (' . $counter . ')';
            $counter++;
        }

        $maxSort = (int) OrderItem::where('event_day_id', $dayId)->max('sort_order');

        $orderItem = OrderItem::create([
            'team_id'      => $teamId,
            'user_id'      => Auth::id(),
            'event_day_id' => $dayId,
            'typ'          => $typ,
            'status'       => 'Offen',
            'lieferant'    => '',
            'artikel'      => (int) $quoteItem->artikel,
            'positionen'   => (int) $quoteItem->positionen,
            'einkauf'      => 0,
            'sort_order'   => $maxSort + 1,
        ]);

        self::copyPositions($quoteItem, $orderItem, $teamId);
        self::syncOrderItemSummary($orderItem);

        return $orderItem->fresh();
    }

    /**
     * Sucht einen passenden OrderItem fuer den QuoteItem (gleicher Tag, Typ
     * exakt oder Basis-Typ + "(N)"-Suffix) und ersetzt dessen Positionen.
     * Gibt den aktualisierten OrderItem oder null zurueck, wenn keiner passt.
     */
    public static function syncItem(QuoteItem $quoteItem): ?OrderItem
    {
        $quoteItem->loadMissing('posList');
        $dayId = $quoteItem->event_day_id;
        $baseTyp = preg_replace('/\s*\(\d+\)$/', '', (string) $quoteItem->typ);

        $orderItem = OrderItem::where('event_day_id', $dayId)
            ->where(function ($q) use ($quoteItem, $baseTyp) {
                $q->where('typ', $quoteItem->typ)
                  ->orWhere('typ', $baseTyp)
                  ->orWhere('typ', 'like', $baseTyp . ' (%');
            })
            ->first();

        if (!$orderItem) return null;

        $teamId = $quoteItem->team_id ?? $orderItem->team_id;

        $orderItem->posList()->delete();
        self::copyPositions($quoteItem, $orderItem, $teamId);
        self::syncOrderItemSummary($orderItem);

        return $orderItem->fresh();
    }

    /**
     * Konvertiert alle QuoteItems eines Tages. Gibt die erzeugten OrderItems
     * zurueck.
     */
    public static function convertAllForDay(Event $event, int $dayId): Collection
    {
        $items = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))
            ->where('event_day_id', $dayId)
            ->orderBy('sort_order')
            ->get();
        return $items->map(fn ($it) => self::convertItem($it));
    }

    /**
     * Konvertiert alle QuoteItems eines Events. Gibt die erzeugten OrderItems
     * zurueck.
     */
    public static function convertAllForEvent(Event $event): Collection
    {
        $items = QuoteItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))
            ->orderBy('sort_order')
            ->get();
        return $items->map(fn ($it) => self::convertItem($it));
    }

    /**
     * Aktualisiert artikel/positionen-Count und einkauf-Summe auf dem
     * OrderItem. artikel = alle nicht-Baustein-Zeilen, positionen = alle.
     */
    public static function syncOrderItemSummary(OrderItem $item): void
    {
        $positions = $item->posList()->get();

        $bausteinNames = collect(SettingsService::bausteine($item->team_id))
            ->map(fn ($b) => mb_strtolower(trim((string) ($b['name'] ?? ''))))
            ->filter()
            ->all();
        $isBaustein = fn ($gruppe) => in_array(mb_strtolower(trim((string) $gruppe)), $bausteinNames, true);

        $item->update([
            'artikel'    => $positions->filter(fn ($p) => !$isBaustein($p->gruppe))->count(),
            'positionen' => $positions->count(),
            'einkauf'    => (float) $positions->sum('ek'),
        ]);
    }

    protected static function copyPositions(QuoteItem $from, OrderItem $to, ?int $teamId): void
    {
        foreach ($from->posList as $pos) {
            OrderPosition::create([
                'team_id'       => $teamId,
                'user_id'       => Auth::id(),
                'order_item_id' => $to->id,
                'gruppe'        => $pos->gruppe,
                'name'          => $pos->name,
                'anz'           => $pos->anz,
                'anz2'          => $pos->anz2,
                'uhrzeit'       => $pos->uhrzeit,
                'bis'           => $pos->bis,
                'inhalt'        => $pos->inhalt,
                'gebinde'       => $pos->gebinde,
                'basis_ek'      => $pos->basis_ek,
                'ek'            => $pos->ek,
                'preis'         => $pos->preis,
                'mwst'          => $pos->mwst,
                'gesamt'        => $pos->gesamt,
                'bemerkung'     => $pos->bemerkung,
                'sort_order'    => $pos->sort_order,
            ]);
        }
    }
}
