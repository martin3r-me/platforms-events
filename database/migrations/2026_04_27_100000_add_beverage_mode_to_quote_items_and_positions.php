<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_quote_items', function (Blueprint $table) {
            if (!Schema::hasColumn('events_quote_items', 'beverage_mode')) {
                // null = kein Getraenke-Modus gesetzt; wird nur fuer Getraenke-typische
                // Vorgaenge benoetigt. Werte z. B. "verbrauch", "alternativ", "auf_anfrage"
                // (frei konfigurierbar in den Events-Settings).
                $table->string('beverage_mode', 32)->nullable()->after('typ');
            }
        });

        Schema::table('events_quote_positionen', function (Blueprint $table) {
            if (!Schema::hasColumn('events_quote_positionen', 'beverage_mode')) {
                // null = erbt vom Vorgang (events_quote_items.beverage_mode);
                // gesetzt = explizites Position-Override.
                $table->string('beverage_mode', 32)->nullable()->after('gruppe');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events_quote_positionen', function (Blueprint $table) {
            if (Schema::hasColumn('events_quote_positionen', 'beverage_mode')) {
                $table->dropColumn('beverage_mode');
            }
        });

        Schema::table('events_quote_items', function (Blueprint $table) {
            if (Schema::hasColumn('events_quote_items', 'beverage_mode')) {
                $table->dropColumn('beverage_mode');
            }
        });
    }
};
