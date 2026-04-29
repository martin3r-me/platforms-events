<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * inhalt war VARCHAR(50) – zu klein fuer realistische Article-Descriptions.
 * Wir erweitern auf TEXT in events_quote_positionen UND events_order_positionen,
 * damit weder Apply-Package- noch Apply-Location-Pricing-Pfad in einen
 * SQL-Truncation-Crash laeuft.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events_quote_positionen')) {
            Schema::table('events_quote_positionen', function (Blueprint $table) {
                $table->text('inhalt')->nullable()->change();
            });
        }
        if (Schema::hasTable('events_order_positionen')) {
            Schema::table('events_order_positionen', function (Blueprint $table) {
                $table->text('inhalt')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Achtung: Down kuerzt evtl. bestehende Werte. Default '' behalten.
        if (Schema::hasTable('events_quote_positionen')) {
            Schema::table('events_quote_positionen', function (Blueprint $table) {
                $table->string('inhalt', 50)->default('')->change();
            });
        }
        if (Schema::hasTable('events_order_positionen')) {
            Schema::table('events_order_positionen', function (Blueprint $table) {
                $table->string('inhalt', 50)->default('')->change();
            });
        }
    }
};
