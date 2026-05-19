<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vereinheitlicht die Zeit-Spalten ueber alle Tabellen auf `start_time` /
 * `end_time`. Vorher waren drei Konventionen im Umlauf:
 *
 *   - events_event_days        : von / bis
 *   - events_schedule_items    : von / bis
 *   - events_bookings          : beginn / ende
 *   - events_quote_positionen  : uhrzeit / bis
 *   - events_order_positionen  : uhrzeit / bis
 *
 * Nach der Migration: alle fuenf Tabellen → start_time / end_time.
 *
 * Idempotent: prueft pro Spalte ob umbenannt werden muss.
 */
return new class extends Migration
{
    /** @var array<string,array{string,string}> */
    private const MAP = [
        'events_event_days'       => ['von' => 'start_time', 'bis' => 'end_time'],
        'events_schedule_items'   => ['von' => 'start_time', 'bis' => 'end_time'],
        'events_bookings'         => ['beginn' => 'start_time', 'ende' => 'end_time'],
        'events_quote_positionen' => ['uhrzeit' => 'start_time', 'bis' => 'end_time'],
        'events_order_positionen' => ['uhrzeit' => 'start_time', 'bis' => 'end_time'],
    ];

    public function up(): void
    {
        foreach (self::MAP as $table => $renames) {
            if (!Schema::hasTable($table)) continue;

            foreach ($renames as $from => $to) {
                if (Schema::hasColumn($table, $to)) {
                    // Zielspalte existiert schon (z.B. teil-migrierter Stand) — skip.
                    continue;
                }
                if (!Schema::hasColumn($table, $from)) {
                    continue;
                }
                Schema::table($table, function (Blueprint $t) use ($from, $to) {
                    $t->renameColumn($from, $to);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $table => $renames) {
            if (!Schema::hasTable($table)) continue;

            foreach ($renames as $from => $to) {
                if (!Schema::hasColumn($table, $to)) continue;
                if (Schema::hasColumn($table, $from)) continue;
                Schema::table($table, function (Blueprint $t) use ($from, $to) {
                    $t->renameColumn($to, $from);
                });
            }
        }
    }
};
