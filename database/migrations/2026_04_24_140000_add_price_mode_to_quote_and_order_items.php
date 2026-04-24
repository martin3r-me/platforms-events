<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['events_quote_items', 'events_order_items'] as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'price_mode')) {
                    $t->string('price_mode', 10)->default('netto')->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['events_quote_items', 'events_order_items'] as $table) {
            if (!Schema::hasTable($table)) continue;
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'price_mode')) {
                    $t->dropColumn('price_mode');
                }
            });
        }
    }
};
