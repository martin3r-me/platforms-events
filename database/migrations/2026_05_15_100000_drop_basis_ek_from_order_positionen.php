<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bestell-Positionen brauchen kein basis_ek (anders als Angebots-Positionen):
 * EK ist hier die einzige relevante Einkaufsgroesse. Spalte raus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events_order_positionen', 'basis_ek')) {
            return;
        }
        Schema::table('events_order_positionen', function (Blueprint $table) {
            $table->dropColumn('basis_ek');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('events_order_positionen', 'basis_ek')) {
            return;
        }
        Schema::table('events_order_positionen', function (Blueprint $table) {
            $table->decimal('basis_ek', 10, 2)->default(0)->after('gebinde');
        });
    }
};
