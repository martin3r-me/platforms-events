<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('events_order_items', 'order_form_mode')) {
            return;
        }

        Schema::table('events_order_items', function (Blueprint $table) {
            // Steuert die Sichtbarkeit des Bestellscheins fuer diesen Vorgang:
            //   auto - abgeleitet aus den Positionen (mind. eine supplier-Position)
            //   on   - immer anzeigen
            //   off  - nie anzeigen
            $table->string('order_form_mode', 8)->default('auto')->after('bemerkung');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('events_order_items', 'order_form_mode')) {
            return;
        }

        Schema::table('events_order_items', function (Blueprint $table) {
            $table->dropColumn('order_form_mode');
        });
    }
};
