<?php

namespace Platform\Events\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Events\Models\LocationPricingApplication;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;
use Platform\Locations\Models\Location;
use Platform\Locations\Models\LocationAddon;
use Platform\Locations\Models\LocationPricing;

/**
 * Buchen von Location-Pricings + optionalen Add-ons in einen QuoteItem.
 *
 * Pattern analog FlatRateApplicator:
 *  - idempotent: pro (quote_item_id, location_id) gibt es genau eine
 *    aktive (non-superseded) Application. Beim Re-Apply werden alte
 *    QuotePositions geloescht (Soft-Delete) und die alte Application
 *    markiert (superseded_at).
 *  - Audit-Trail in events_location_pricing_applications:
 *    input_snapshot mit EventDay.day_type, gewaehlte IDs, Mengen, Warnings;
 *    created_positions als Backreference auf erzeugte QuotePosition-IDs.
 *
 * Tag-Typ-Match: liegt vor dem Apply (Picker waehlt passendes Pricing
 * vor; bei Mismatch erscheint ein Warning-Banner, der Service buchst
 * nur ein, was uebergeben wurde — keine eigene Skip-Heuristik).
 */
class LocationPricingApplicator
{
    public const DEFAULT_PRICING_GRUPPE = 'Miete';
    public const DEFAULT_ADDON_GRUPPE   = 'Mietleistung';
    public const DEFAULT_MWST           = '19%';

    /**
     * Wendet eine Auswahl von Pricings/Add-ons der Location auf den QuoteItem an.
     *
     * @param  QuoteItem $target
     * @param  Location  $location
     * @param  array{pricing_ids?: array<int,int>, addon_selections?: array<int,array{addon_id:int, qty?:int|float|string}>} $selection
     *
     * @return array{
     *   positions: array<int, QuotePosition>,
     *   application: LocationPricingApplication,
     *   warnings: array<int, string>
     * }
     */
    public static function apply(QuoteItem $target, Location $location, array $selection): array
    {
        $target->loadMissing(['eventDay.event']);
        $event = $target->eventDay?->event;
        $day   = $target->eventDay;

        if (!$event || !$day) {
            throw new \RuntimeException('Vorgang hat keinen zugehoerigen EventDay bzw. Event.');
        }

        if ($location->team_id !== $event->team_id) {
            throw new \RuntimeException('Location gehoert zu einem anderen Team als das Event.');
        }

        $teamId = $target->team_id ?? $event->team_id;

        $pricingIds = collect($selection['pricing_ids'] ?? [])->map(fn ($v) => (int) $v)->filter()->values();
        $addonSels  = collect($selection['addon_selections'] ?? [])->filter(fn ($r) => !empty($r['addon_id']))->values();

        if ($pricingIds->isEmpty() && $addonSels->isEmpty()) {
            throw new \RuntimeException('Keine Pricings oder Add-ons ausgewaehlt.');
        }

        // Tatsaechliche Datensaetze laden (nur die der gegebenen Location)
        $pricings = $pricingIds->isEmpty()
            ? collect()
            : $location->pricings()->whereIn('id', $pricingIds)->get()->keyBy('id');

        $addonIds = $addonSels->pluck('addon_id')->map(fn ($v) => (int) $v)->filter()->values();
        $addons = $addonIds->isEmpty()
            ? collect()
            : $location->addons()->whereIn('id', $addonIds)->get()->keyBy('id');

        // Snapshot der Tages-Statistik fuer Mengen-Defaults (pro_tag / pro_va_tag)
        $stats = self::dayStats($event, $day);

        $warnings = [];

        return DB::transaction(function () use ($target, $location, $teamId, $pricings, $addonSels, $addons, $stats, $day, &$warnings) {

            // 1) Bestehende aktive Application zu (quote_item_id, location_id) holen
            $existing = LocationPricingApplication::where('quote_item_id', $target->id)
                ->where('location_id', $location->id)
                ->whereNull('superseded_at')
                ->first();

            if ($existing) {
                // Alte erzeugte QuotePositions soft-loeschen
                $oldIds = $existing->quotePositionIds();
                if (!empty($oldIds)) {
                    QuotePosition::whereIn('id', $oldIds)->delete();
                }
                $existing->update(['superseded_at' => now()]);
            }

            $maxSort = (int) QuotePosition::where('quote_item_id', $target->id)->max('sort_order');
            $createdPositions = [];
            $createdRefs = [];

            // 2) Pricings einbuchen
            foreach ($pricings as $pricing) {
                /** @var LocationPricing $pricing */
                $price = (float) $pricing->price_net;
                $name  = $pricing->displayLabel();

                $maxSort++;
                $pos = QuotePosition::create([
                    'team_id'       => $teamId,
                    'user_id'       => Auth::id(),
                    'quote_item_id' => $target->id,
                    'gruppe'        => self::DEFAULT_PRICING_GRUPPE,
                    'name'          => $name,
                    'anz'           => '1',
                    'preis'         => $price,
                    'mwst'          => self::DEFAULT_MWST,
                    'gesamt'        => $price,
                    'sort_order'    => $maxSort,
                ]);

                $createdPositions[] = $pos;
                $createdRefs[] = [
                    'quote_position_id' => $pos->id,
                    'source'            => 'pricing',
                    'ref_id'            => $pricing->id,
                    'ref_uuid'          => $pricing->uuid,
                ];
            }

            // 3) Add-ons einbuchen
            foreach ($addonSels as $sel) {
                $aid = (int) $sel['addon_id'];
                /** @var LocationAddon|null $addon */
                $addon = $addons->get($aid);
                if (!$addon) {
                    $warnings[] = "Add-on {$aid} nicht gefunden oder nicht zur Location {$location->id} gehoerig — uebersprungen.";
                    continue;
                }

                // Menge bestimmen: User-Vorgabe schlaegt unit-Default
                $userQty = isset($sel['qty']) ? (float) preg_replace('/[^0-9.]/', '', (string) $sel['qty']) : null;
                $qty = $userQty !== null && $userQty > 0
                    ? $userQty
                    : self::defaultQtyForUnit($addon->unit, $stats);

                if ($qty <= 0) {
                    $warnings[] = "Add-on '{$addon->label}' ergab Menge 0 (unit={$addon->unit}) — uebersprungen.";
                    continue;
                }

                $price  = (float) $addon->price_net;
                $gesamt = round($qty * $price, 2);

                $maxSort++;
                $pos = QuotePosition::create([
                    'team_id'       => $teamId,
                    'user_id'       => Auth::id(),
                    'quote_item_id' => $target->id,
                    'gruppe'        => self::DEFAULT_ADDON_GRUPPE,
                    'name'          => $addon->label . ' (' . $addon->unitLabel() . ')',
                    'anz'           => self::formatAnz($qty),
                    'preis'         => $price,
                    'mwst'          => self::DEFAULT_MWST,
                    'gesamt'        => $gesamt,
                    'sort_order'    => $maxSort,
                ]);

                $createdPositions[] = $pos;
                $createdRefs[] = [
                    'quote_position_id' => $pos->id,
                    'source'            => 'addon',
                    'ref_id'            => $addon->id,
                    'ref_uuid'          => $addon->uuid,
                    'qty'               => $qty,
                    'unit'              => $addon->unit,
                ];
            }

            // 4) Application schreiben
            $application = LocationPricingApplication::create([
                'team_id'           => $teamId,
                'user_id'           => Auth::id(),
                'quote_item_id'     => $target->id,
                'location_id'       => $location->id,
                'input_snapshot'    => [
                    'day' => [
                        'id'       => $day->id,
                        'datum'    => $day->datum?->format('Y-m-d'),
                        'day_type' => (string) ($day->day_type ?? ''),
                    ],
                    'stats'        => $stats,
                    'pricings'     => $pricings->values()->map(fn ($p) => [
                        'id'             => $p->id,
                        'uuid'           => $p->uuid,
                        'day_type_label' => $p->day_type_label,
                        'price_net'      => (float) $p->price_net,
                    ])->all(),
                    'addons'       => $addonSels->map(fn ($s) => [
                        'addon_id' => (int) $s['addon_id'],
                        'qty'      => $s['qty'] ?? null,
                    ])->all(),
                    'warnings'     => $warnings,
                    'location_uuid' => $location->uuid,
                ],
                'created_positions' => $createdRefs,
            ]);

            self::recalculateItem($target);

            return [
                'positions'   => $createdPositions,
                'application' => $application,
                'warnings'    => $warnings,
            ];
        });
    }

    /**
     * Entfernt eine aktive Anwendung: loescht erzeugte QuotePositions
     * (Soft Delete), markiert die Application als superseded, rechnet
     * den QuoteItem-Rollup neu. Audit bleibt erhalten.
     */
    public static function remove(LocationPricingApplication $application): void
    {
        if ($application->superseded_at) {
            return;
        }

        DB::transaction(function () use ($application) {
            $ids = $application->quotePositionIds();
            if (!empty($ids)) {
                QuotePosition::whereIn('id', $ids)->delete();
            }
            $application->update(['superseded_at' => now()]);

            $item = $application->quoteItem;
            if ($item) {
                self::recalculateItem($item);
            }
        });
    }

    /**
     * Liefert eine Vorschlags-Selection fuer den Picker:
     *  - alle Pricings fuer den day_type des EventDays vorausgewaehlt
     *  - keine Add-ons vorausgewaehlt (User entscheidet)
     *
     * @return array{warnings: array<int,string>, suggested_pricing_ids: array<int,int>}
     */
    public static function suggestSelection(QuoteItem $target, Location $location): array
    {
        $warnings = [];
        $suggested = [];

        $target->loadMissing(['eventDay']);
        $day = $target->eventDay;
        if (!$day) {
            return ['warnings' => ['Kein EventDay verknuepft.'], 'suggested_pricing_ids' => []];
        }

        $dayType = (string) ($day->day_type ?? '');
        if ($dayType === '') {
            $warnings[] = 'EventDay hat keinen day_type gesetzt.';
            return ['warnings' => $warnings, 'suggested_pricing_ids' => []];
        }

        $matches = $location->pricings()->where('day_type_label', $dayType)->get();
        if ($matches->isEmpty()) {
            $warnings[] = "Kein Pricing fuer day_type '{$dayType}' an Location '{$location->name}' gepflegt.";
            return ['warnings' => $warnings, 'suggested_pricing_ids' => []];
        }

        foreach ($matches as $p) {
            $suggested[] = (int) $p->id;
        }

        return ['warnings' => $warnings, 'suggested_pricing_ids' => $suggested];
    }

    /**
     * Default-Menge fuer eine Add-on-Einheit anhand der Tages-Statistik.
     */
    protected static function defaultQtyForUnit(string $unit, array $stats): float
    {
        return match ($unit) {
            LocationAddon::UNIT_EINMALIG    => 1.0,
            LocationAddon::UNIT_PRO_STUECK  => 1.0,
            LocationAddon::UNIT_PRO_TAG     => (float) ($stats['days_total'] ?? 0),
            LocationAddon::UNIT_PRO_VA_TAG  => (float) ($stats['days_va'] ?? 0),
            default                         => 1.0,
        };
    }

    /**
     * @return array{days_total:int, days_va:int, va_label:string}
     */
    protected static function dayStats($event, $day): array
    {
        $allDays = $event->days()->get();
        $vaLabel = self::resolveVaLabel($event->team_id);
        $vaCount = $allDays->filter(fn ($d) => trim((string) ($d->day_type ?? '')) === $vaLabel)->count();

        return [
            'days_total' => $allDays->count(),
            'days_va'    => $vaCount,
            'va_label'   => $vaLabel,
        ];
    }

    /**
     * Liefert das Volltext-Label fuer "Veranstaltungstag" aus den Settings
     * (erstes Element in dayTypes), Default 'Veranstaltungstag'.
     */
    protected static function resolveVaLabel(int $teamId): string
    {
        try {
            $types = SettingsService::dayTypes($teamId);
            $first = $types[0] ?? null;
            return is_string($first) && $first !== '' ? $first : 'Veranstaltungstag';
        } catch (\Throwable $e) {
            return 'Veranstaltungstag';
        }
    }

    protected static function formatAnz(float $qty): string
    {
        // Ganzzahlig wenn moeglich
        if (abs($qty - round($qty)) < 0.0001) {
            return (string) (int) round($qty);
        }
        return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    }

    /**
     * Gleiche Logik wie in FlatRateApplicator::recalculateItem().
     */
    protected static function recalculateItem(QuoteItem $item): void
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
            'umsatz'     => (float) $positions->sum('gesamt'),
        ]);
    }
}
