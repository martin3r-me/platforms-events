<?php

namespace Platform\Events\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Platform\Events\Models\Event;
use Platform\Events\Models\EventDay;
use Platform\Events\Models\FlatRateApplication;
use Platform\Events\Models\FlatRateRule;
use Platform\Events\Models\OrderItem;
use Platform\Events\Models\QuoteItem;
use Platform\Events\Models\QuotePosition;

/**
 * Orchestriert die Anwendung einer Pauschal-Regel auf einen konkreten
 * QuoteItem: Kontext zusammenbauen, Engine aufrufen, QuotePosition
 * anlegen/aktualisieren, Application-Audit schreiben.
 *
 * Idempotent: pro (rule_id, quote_item_id) existiert genau eine aktive
 * (non-superseded) Application. Erneutes Anwenden ueberschreibt statt
 * zu duplizieren.
 *
 * Analog zum Muster in ArticlePackageApplicator.
 */
class FlatRateApplicator
{
    /**
     * @return array{position: QuotePosition, application: FlatRateApplication, value: float, breakdown: array}
     *
     * @throws \RuntimeException bei Validierungs-/Formel-Fehlern
     */
    public static function apply(FlatRateRule $rule, QuoteItem $target): array
    {
        $target->loadMissing(['eventDay.event']);
        $event   = $target->eventDay?->event;
        $day     = $target->eventDay;

        if (!$event || !$day) {
            throw new \RuntimeException('Vorgang hat keinen zugehoerigen EventDay bzw. Event.');
        }

        $teamId = $target->team_id ?? $event->team_id;

        $allowedGruppen = PositionValidator::allowedGruppen($teamId);
        if (!in_array((string) $rule->output_gruppe, $allowedGruppen, true)) {
            throw new \RuntimeException('Output-Gruppe "' . $rule->output_gruppe . '" existiert nicht (mehr) im Artikelstamm.');
        }

        $context = self::buildContext($event, $day, $target);

        $result = FlatRateEngine::evaluate((string) $rule->formula, $context);
        if (!$result['ok']) {
            $rule->update(['last_error' => $result['error'], 'last_error_at' => now()]);
            throw new \RuntimeException('Formel-Fehler: ' . $result['error']);
        }

        $value = round((float) $result['value'], 2);

        // last_error zurueckfallen lassen bei erfolgreichem Apply
        if ($rule->last_error) {
            $rule->update(['last_error' => null, 'last_error_at' => null]);
        }

        return DB::transaction(function () use ($rule, $target, $teamId, $value, $context) {
            $existing = FlatRateApplication::where('rule_id', $rule->id)
                ->where('quote_item_id', $target->id)
                ->whereNull('superseded_at')
                ->first();

            $position = $existing?->quotePosition;

            if ($position) {
                $position->update([
                    'gruppe'  => $rule->output_gruppe,
                    'name'    => $rule->output_name,
                    'anz'     => '1',
                    'preis'   => $value,
                    'mwst'    => $rule->output_mwst ?: '19%',
                    'gesamt'  => $value,
                    'procurement_type' => $rule->output_procurement_type,
                ]);
            } else {
                $maxSort = (int) QuotePosition::where('quote_item_id', $target->id)->max('sort_order');
                $position = QuotePosition::create([
                    'team_id'          => $teamId,
                    'user_id'          => Auth::id(),
                    'quote_item_id'    => $target->id,
                    'gruppe'           => $rule->output_gruppe,
                    'name'             => $rule->output_name,
                    'anz'              => '1',
                    'preis'            => $value,
                    'mwst'             => $rule->output_mwst ?: '19%',
                    'gesamt'           => $value,
                    'procurement_type' => $rule->output_procurement_type,
                    'sort_order'       => $maxSort + 1,
                ]);
            }

            if ($existing) {
                $existing->update(['superseded_at' => now()]);
            }

            $application = FlatRateApplication::create([
                'team_id'           => $teamId,
                'user_id'           => Auth::id(),
                'rule_id'           => $rule->id,
                'quote_item_id'     => $target->id,
                'quote_position_id' => $position->id,
                'input_snapshot'    => $context,
                'result_value'      => $value,
                'result_breakdown'  => [
                    'formula'  => (string) $rule->formula,
                    'rule_uuid' => $rule->uuid,
                ],
            ]);

            self::recalculateItem($target);

            return [
                'position'    => $position,
                'application' => $application,
                'value'       => $value,
                'breakdown'   => (array) $application->result_breakdown,
            ];
        });
    }

    /**
     * Entfernt eine aktive Pauschal-Anwendung: loescht die erzeugte
     * QuotePosition, markiert die Application als superseded, rechnet den
     * QuoteItem-Rollup neu. Altdaten bleiben fuer Audit erhalten.
     */
    public static function remove(FlatRateApplication $application): void
    {
        if ($application->superseded_at) return;

        DB::transaction(function () use ($application) {
            $position = $application->quotePosition;
            $item     = $application->quoteItem;

            if ($position) {
                $position->delete();
            }
            $application->update(['superseded_at' => now()]);

            if ($item) {
                self::recalculateItem($item);
            }
        });
    }

    /**
     * Fuehrt eine reine Dry-Run-Auswertung durch (ohne Persistierung).
     *
     * @return array{ok: bool, value: ?float, error: ?string, context: array}
     */
    public static function dryRun(FlatRateRule $rule, QuoteItem $target): array
    {
        $target->loadMissing(['eventDay.event']);
        $event = $target->eventDay?->event;
        $day   = $target->eventDay;
        if (!$event || !$day) {
            return ['ok' => false, 'value' => null, 'error' => 'Vorgang hat keinen EventDay.', 'context' => []];
        }

        $context = self::buildContext($event, $day, $target);
        $result  = FlatRateEngine::evaluate((string) $rule->formula, $context);
        return $result + ['context' => $context];
    }

    /**
     * Baut den Kontext, der als Variable-Scope an die ExpressionLanguage geht.
     */
    public static function buildContext(Event $event, EventDay $day, QuoteItem $target): array
    {
        $positions = $target->posList()->get();
        $sumEk     = (float) $positions->sum('ek');
        $sumGes    = (float) $positions->sum('gesamt');
        $priceMode = (string) ($event->quote_price_mode ?? 'netto');

        // Netto-Umsatz herleiten: bei brutto-mode muss pro Position durch (1+mwst) geteilt werden.
        $sumVkNetto = 0.0;
        foreach ($positions as $p) {
            $g = (float) $p->gesamt;
            if ($priceMode === 'brutto') {
                $pct = (float) filter_var((string) ($p->mwst ?? '0%'), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $g = $pct > 0 ? $g / (1 + $pct / 100) : $g;
            }
            $sumVkNetto += $g;
        }

        // Pro Gruppe: Summe (gesamt), Anzahl Positionen, Summe Mengen (anz),
        // sowie EK-Summe — damit Formeln z.B. "pro gebuchtem Bier 2 Euro" oder
        // "30% Aufschlag auf Getränke-EK" sauber rechnen koennen.
        $sumByGruppe      = [];
        $countByGruppe    = [];
        $anzByGruppe      = [];
        $sumEkByGruppe    = [];
        foreach ($positions as $p) {
            $key = (string) $p->gruppe;
            if ($key === '') $key = '_ohne';
            $sumByGruppe[$key]    = round(((float) ($sumByGruppe[$key] ?? 0)) + (float) $p->gesamt, 2);
            $countByGruppe[$key]  = (int) ($countByGruppe[$key] ?? 0) + 1;
            $anzByGruppe[$key]    = round(((float) ($anzByGruppe[$key] ?? 0)) + (float) preg_replace('/[^0-9.-]/', '', (string) $p->anz), 2);
            $sumEkByGruppe[$key]  = round(((float) ($sumEkByGruppe[$key] ?? 0)) + (float) $p->ek, 2);
        }

        $sumAnz = array_sum($anzByGruppe);

        // Andere Vorgaenge des gleichen EventDay fuer "items.sum_by_typ"
        $sumByTyp = [];
        $otherItems = QuoteItem::where('event_day_id', $day->id)
            ->where('id', '!=', $target->id)
            ->get();
        foreach ($otherItems as $oi) {
            $typ = (string) $oi->typ;
            $sumByTyp[$typ] = round(((float) ($sumByTyp[$typ] ?? 0)) + (float) $oi->umsatz, 2);
        }

        $persVon = (float) preg_replace('/[^0-9.]/', '', (string) ($day->pers_von ?? ''));
        $persBis = (float) preg_replace('/[^0-9.]/', '', (string) ($day->pers_bis ?? ''));
        if ($persVon <= 0) $persVon = $persBis;
        if ($persBis <= 0) $persBis = $persVon;
        $persAvg = $persVon > 0 && $persBis > 0 ? round(($persVon + $persBis) / 2, 2) : 0.0;

        $durationHours = self::durationHours((string) ($day->von ?? ''), (string) ($day->bis ?? ''));
        $durationDays  = $event->days()->count();

        $startIso = $event->start_date?->format('Y-m-d') ?: $day->datum?->format('Y-m-d') ?: now()->toDateString();
        $month    = (int) date('n', strtotime($startIso) ?: time());
        $season   = match (true) {
            $month <= 2 || $month === 12 => 'winter',
            $month <= 5                  => 'spring',
            $month <= 8                  => 'summer',
            default                      => 'autumn',
        };

        $children = (int) ($day->children_count ?? 0);
        $adults   = max(0, ((int) round($persAvg)) - $children);

        $dayIso = $day->datum?->format('Y-m-d') ?: $startIso;
        $weekday = self::weekdayShort($dayIso);
        $dow = (int) date('w', strtotime($dayIso) ?: time());

        return [
            'event' => [
                'type'          => (string) ($event->event_type ?? ''),
                'group'         => (string) ($event->group ?? ''),
                'duration_days' => (int) $durationDays,
                'season'        => $season,
                'month'         => $month,
            ],
            'day' => [
                'duration_hours' => $durationHours,
                'pers_min'       => $persVon,
                'pers_max'       => $persBis,
                'pers_avg'       => $persAvg,
                'split_a'        => (int) ($day->split_a ?? 50),
                'split_b'        => 100 - (int) ($day->split_a ?? 50),
                'children'       => $children,
                'adults'         => $adults,
                'weekday'        => $weekday,
                'is_weekend'     => $dow === 0 || $dow === 6,
                'datum'          => $dayIso,
            ],
            'item' => [
                'sum_ek'          => round($sumEk, 2),
                'sum_vk_netto'    => round($sumVkNetto, 2),
                'sum_gesamt'      => round($sumGes, 2),
                'count'           => $positions->count(),
                'sum_anz'         => round((float) $sumAnz, 2),
                'price_mode'      => $priceMode,
                'sum_by_gruppe'   => $sumByGruppe,
                'count_by_gruppe' => $countByGruppe,
                'anz_by_gruppe'   => $anzByGruppe,
                'ek_by_gruppe'    => $sumEkByGruppe,
                'sum_by_typ'      => $sumByTyp,
                'typ'             => (string) $target->typ,
            ],
        ];
    }

    protected static function durationHours(string $von, string $bis): float
    {
        if ($von === '' || $bis === '') return 0.0;
        $toMin = function (string $t): ?int {
            if (!preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) return null;
            return ((int) $m[1]) * 60 + (int) $m[2];
        };
        $a = $toMin($von);
        $b = $toMin($bis);
        if ($a === null || $b === null) return 0.0;
        $diff = $b - $a;
        if ($diff < 0) $diff += 24 * 60; // ueber Mitternacht
        return round($diff / 60, 2);
    }

    protected static function weekdayShort(string $date): string
    {
        $map = ['Sun'=>'So','Mon'=>'Mo','Tue'=>'Di','Wed'=>'Mi','Thu'=>'Do','Fri'=>'Fr','Sat'=>'Sa'];
        $short = date('D', strtotime($date) ?: time());
        return $map[$short] ?? $short;
    }

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
