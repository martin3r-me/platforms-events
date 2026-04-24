<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            if (!Schema::hasColumn('events_events', 'quote_price_mode')) {
                $table->string('quote_price_mode', 10)->default('netto')->after('status');
            }
        });

        // Backfill: Event erbt "brutto", sobald ein zugehoeriger QuoteItem brutto war.
        if (Schema::hasColumn('events_quote_items', 'price_mode')) {
            $bruttoEventIds = DB::table('events_quote_items as qi')
                ->join('events_event_days as d', 'd.id', '=', 'qi.event_day_id')
                ->where('qi.price_mode', 'brutto')
                ->whereNull('qi.deleted_at')
                ->distinct()
                ->pluck('d.event_id');

            if ($bruttoEventIds->isNotEmpty()) {
                DB::table('events_events')
                    ->whereIn('id', $bruttoEventIds)
                    ->update(['quote_price_mode' => 'brutto']);
            }

            Schema::table('events_quote_items', function (Blueprint $table) {
                $table->dropColumn('price_mode');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('events_quote_items', 'price_mode')) {
            Schema::table('events_quote_items', function (Blueprint $table) {
                $table->string('price_mode', 10)->default('netto')->after('status');
            });
        }

        Schema::table('events_events', function (Blueprint $table) {
            if (Schema::hasColumn('events_events', 'quote_price_mode')) {
                $table->dropColumn('quote_price_mode');
            }
        });
    }
};
