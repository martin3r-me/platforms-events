<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestell-Positionen brauchen kein preis (VK): Einkauf wird ausschliesslich
 * in `ek` gefuehrt, ein Verkaufspreis hat im Order-Pfad keine Bedeutung.
 * Spalte raus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events_order_positionen', 'preis')) {
            return;
        }
        Schema::table('events_order_positionen', function (Blueprint $table) {
            $table->dropColumn('preis');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('events_order_positionen', 'preis')) {
            return;
        }
        Schema::table('events_order_positionen', function (Blueprint $table) {
            $table->decimal('preis', 10, 2)->default(0)->after('ek');
        });
    }
};
