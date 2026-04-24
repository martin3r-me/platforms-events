<?php

namespace Platform\Events\Services;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Zentrale Auswertung von Pauschal-Kalkulations-Formeln.
 *
 * - Symfony ExpressionLanguage in Sandbox-Modus: nur Whitelist-Funktionen,
 *   Context als skalare Arrays (keine Eloquent-Objekte).
 * - Injection-Schutz: keine PHP-Funktionen durchgereicht; `system`, `eval`,
 *   `__construct` etc. scheitern beim Parse-Step.
 */
class FlatRateEngine
{
    protected static ?ExpressionLanguage $el = null;

    /**
     * Wertet eine Formel gegen einen Kontext aus.
     *
     * @param string               $formula Expression-Body (z.B. "day.pers_avg * 20")
     * @param array<string, mixed> $context Whitelisted scalar context
     *
     * @return array{ok: bool, value: ?float, error: ?string}
     */
    public static function evaluate(string $formula, array $context): array
    {
        $formula = trim($formula);
        if ($formula === '') {
            return ['ok' => false, 'value' => null, 'error' => 'Formel ist leer.'];
        }

        try {
            $result = self::engine()->evaluate($formula, $context);
        } catch (\Throwable $e) {
            return ['ok' => false, 'value' => null, 'error' => $e->getMessage()];
        }

        if (is_bool($result)) {
            $result = $result ? 1.0 : 0.0;
        }
        if (!is_numeric($result)) {
            return ['ok' => false, 'value' => null, 'error' => 'Formel liefert keinen numerischen Wert.'];
        }

        return ['ok' => true, 'value' => (float) $result, 'error' => null];
    }

    /**
     * Dokumentations-Metadaten fuer UI-Legende.
     *
     * @return array{variables: array<string, string>, functions: array<string, string>}
     */
    public static function catalog(): array
    {
        return [
            'variables' => [
                'event.type'          => 'Anlass (Freitext)',
                'event.group'         => 'Anlassgruppe',
                'event.duration_days' => 'Anzahl EventDays',
                'event.season'        => "'winter' | 'spring' | 'summer' | 'autumn' (aus Startdatum)",
                'event.month'         => '1–12',

                'day.duration_hours' => 'Dauer des EventDay in Stunden (von/bis)',
                'day.pers_min'       => 'pers_von',
                'day.pers_max'       => 'pers_bis',
                'day.pers_avg'       => 'Mittelwert aus pers_von/pers_bis',
                'day.split_a'        => 'Verteilung A (0–100)',
                'day.split_b'        => 'Verteilung B = 100 − split_a',
                'day.children'       => 'children_count',
                'day.adults'         => 'pers_avg − children',
                'day.weekday'        => "'Mo' | 'Di' | …",
                'day.is_weekend'     => 'true am Sa/So',

                'item.sum_ek'        => 'Einkaufssumme der Positionen im Vorgang',
                'item.sum_vk_netto'  => 'Netto-Verkaufssumme (respektiert quote_price_mode)',
                'item.count'         => 'Anzahl Positionen im Vorgang',
                'item.price_mode'    => "'netto' | 'brutto'",
                'item.sum_by_gruppe' => "Map: sum_by_gruppe['Bier']",
                'item.sum_by_typ'    => "Map aus anderen Vorgaengen des gleichen EventDays",
            ],
            'functions' => [
                'min(a,b,…)'              => 'Minimum',
                'max(a,b,…)'              => 'Maximum',
                'round(x)'                => 'Kaufmaennisch',
                'ceil(x)'                 => 'Aufrunden',
                'floor(x)'                => 'Abrunden',
                'abs(x)'                  => 'Betrag',
                'clamp(x, lo, hi)'        => 'Zwischen lo und hi klemmen',
                'tier(x, [b1, b2, …])'    => 'Liefert den Index der Staffel, in die x faellt',
                'season(dateStr)'         => "'winter' | 'spring' | 'summer' | 'autumn'",
                'weekday(dateStr)'        => "'Mo' | 'Di' | …",
                'is_weekend(dateStr)'     => 'true/false',
            ],
        ];
    }

    protected static function engine(): ExpressionLanguage
    {
        if (self::$el === null) {
            $el = new ExpressionLanguage();
            self::registerFunctions($el);
            self::$el = $el;
        }
        return self::$el;
    }

    protected static function registerFunctions(ExpressionLanguage $el): void
    {
        $expr = fn (string $compiled) => $compiled;

        $el->register('min',   fn (...$args) => $expr('min(' . implode(',', $args) . ')'), fn ($ctx, ...$args) => min(...$args));
        $el->register('max',   fn (...$args) => $expr('max(' . implode(',', $args) . ')'), fn ($ctx, ...$args) => max(...$args));
        $el->register('round', fn ($x) => "round($x)", fn ($ctx, $x) => round((float) $x));
        $el->register('ceil',  fn ($x) => "ceil($x)",  fn ($ctx, $x) => ceil((float) $x));
        $el->register('floor', fn ($x) => "floor($x)", fn ($ctx, $x) => floor((float) $x));
        $el->register('abs',   fn ($x) => "abs($x)",   fn ($ctx, $x) => abs((float) $x));

        $el->register('clamp',
            fn ($x, $lo, $hi) => "max(min($x, $hi), $lo)",
            fn ($ctx, $x, $lo, $hi) => max(min((float) $x, (float) $hi), (float) $lo)
        );

        $el->register('tier',
            fn ($x, $breaks) => "null /* tier not compilable */",
            function ($ctx, $x, $breaks) {
                $x = (float) $x;
                $breaks = is_array($breaks) ? $breaks : [];
                sort($breaks);
                $idx = 0;
                foreach ($breaks as $b) {
                    if ($x >= (float) $b) $idx++; else break;
                }
                return $idx;
            }
        );

        $el->register('season',
            fn ($date) => "null",
            function ($ctx, $date) {
                $m = (int) date('n', strtotime((string) $date) ?: time());
                return match (true) {
                    $m <= 2 || $m === 12 => 'winter',
                    $m <= 5              => 'spring',
                    $m <= 8              => 'summer',
                    default              => 'autumn',
                };
            }
        );

        $el->register('weekday',
            fn ($date) => "null",
            function ($ctx, $date) {
                $map = ['Sun'=>'So','Mon'=>'Mo','Tue'=>'Di','Wed'=>'Mi','Thu'=>'Do','Fri'=>'Fr','Sat'=>'Sa'];
                $short = date('D', strtotime((string) $date) ?: time());
                return $map[$short] ?? $short;
            }
        );

        $el->register('is_weekend',
            fn ($date) => "null",
            function ($ctx, $date) {
                $dow = (int) date('w', strtotime((string) $date) ?: time());
                return $dow === 0 || $dow === 6;
            }
        );
    }
}
