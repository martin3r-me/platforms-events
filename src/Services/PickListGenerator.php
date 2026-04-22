<?php

namespace Platform\Events\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Platform\Events\Models\Event;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\OrderPosition;
use Platform\Events\Models\PickItem;
use Platform\Events\Models\PickList;

/**
 * Baut Packlisten aus Bestell-Vorgaengen. Haelt die Klassifizierungs-Logik
 * (Lager vs Extern vs Kueche) an einem Ort. Der Livewire-Caller ruft zuerst
 * analyze() auf, um unklassifizierte Artikel zu ermitteln und ggf. einen
 * Review-Schritt anzuzeigen, dann generate() mit den User-Entscheidungen.
 */
class PickListGenerator
{
    /**
     * Analysiert Bestell-Positionen des Events: welche landen in der
     * Packliste, welche sind extern/kueche, welche sind unklassifiziert.
     *
     * @return array{total:int, stock:int, skipped:int, bausteine:int,
     *               unclassified: array<string,int>}
     */
    public static function analyze(Event $event): array
    {
        $posList = self::loadOrderPositions($event);
        $bausteinIndex = self::bausteinIndex($event->team_id);
        $articleLookup = ProcurementTypeResolver::buildArticleLookup($event->team_id);

        $stock = $skipped = $bausteine = 0;
        $unclassified = [];

        foreach ($posList as $pos) {
            if (self::isBaustein($pos->gruppe, $bausteinIndex)) {
                $bausteine++;
                continue;
            }
            $type = ProcurementTypeResolver::resolve(
                $pos->procurement_type,
                (string) $pos->name,
                $event->team_id,
                $articleLookup
            );
            if ($type === 'stock') {
                $stock++;
            } elseif ($type === 'supplier' || $type === 'kitchen') {
                $skipped++;
            } else {
                $key = mb_strtolower(trim((string) $pos->name));
                if ($key === '') continue;
                $unclassified[$key] = [
                    'name'  => (string) $pos->name,
                    'count' => ($unclassified[$key]['count'] ?? 0) + 1,
                ];
            }
        }

        return [
            'total'        => count($posList),
            'stock'        => $stock,
            'skipped'      => $skipped,
            'bausteine'    => $bausteine,
            'unclassified' => array_values($unclassified),
        ];
    }

    /**
     * Erzeugt die Packliste. $classificationOverrides: name(lower) -> 'stock'|'supplier'|'kitchen'|'ignore'
     * (Entscheidungen fuer zuvor unklassifizierte Eintraege).
     */
    public static function generate(Event $event, array $classificationOverrides = [], ?string $title = null): ?PickList
    {
        $posList = self::loadOrderPositions($event);
        if (empty($posList)) return null;

        $bausteinIndex = self::bausteinIndex($event->team_id);
        $articleLookup = ProcurementTypeResolver::buildArticleLookup($event->team_id);

        $list = PickList::create([
            'team_id'    => $event->team_id,
            'user_id'    => Auth::id(),
            'event_id'   => $event->id,
            'title'      => $title ?: 'Generiert aus Bestellungen ' . now()->format('d.m.Y'),
            'status'     => 'open',
            'token'      => Str::random(48),
            'created_by' => Auth::user()?->name,
        ]);

        $sort = 0;
        foreach ($posList as $pos) {
            if (self::isBaustein($pos->gruppe, $bausteinIndex)) continue;

            $type = ProcurementTypeResolver::resolve(
                $pos->procurement_type,
                (string) $pos->name,
                $event->team_id,
                $articleLookup
            );

            if ($type === null) {
                // Unklassifiziert -> User-Entscheidung anwenden
                $key = mb_strtolower(trim((string) $pos->name));
                $decision = $classificationOverrides[$key] ?? 'ignore';
                if ($decision === 'ignore') continue;
                $type = in_array($decision, ['stock','supplier','kitchen'], true) ? $decision : 'ignore';
                if ($type === 'ignore') continue;
            }

            if ($type !== 'stock') continue; // supplier/kitchen gehen nicht in die Packliste

            PickItem::create([
                'team_id'      => $event->team_id,
                'user_id'      => Auth::id(),
                'pick_list_id' => $list->id,
                'name'         => $pos->name,
                'quantity'     => (int) (((float) $pos->anz) ?: 1),
                'gebinde'      => $pos->gebinde,
                'gruppe'       => $pos->gruppe ?: ($pos->order_item?->typ ?? ''),
                'status'       => 'open',
                'sort_order'   => $sort++,
            ]);
        }

        return $list;
    }

    /**
     * @return \Illuminate\Support\Collection<OrderPosition>
     */
    protected static function loadOrderPositions(Event $event)
    {
        return OrderItem::whereHas('eventDay', fn($q) => $q->where('event_id', $event->id))
            ->with('posList')
            ->get()
            ->flatMap(fn ($oi) => $oi->posList->each(fn ($p) => $p->setRelation('order_item', $oi)));
    }

    protected static function bausteinIndex(int $teamId): array
    {
        return collect(SettingsService::bausteine($teamId))
            ->map(fn ($b) => mb_strtolower(trim((string) ($b['name'] ?? ''))))
            ->filter()
            ->flip()
            ->all();
    }

    protected static function isBaustein(?string $gruppe, array $bausteinIndex): bool
    {
        $key = mb_strtolower(trim((string) $gruppe));
        return $key !== '' && isset($bausteinIndex[$key]);
    }
}
