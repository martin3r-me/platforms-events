<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['events_quote_positionen', 'events_order_positionen'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                // Override fuer einzelne Position: null = vom Artikel erben / default stock.
                $t->string('procurement_type', 16)->nullable()->after('mwst');
            });
        }
    }

    public function down(): void
    {
        foreach (['events_quote_positionen', 'events_order_positionen'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('procurement_type');
            });
        }
    }
};
