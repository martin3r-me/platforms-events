<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_order_items', function (Blueprint $table) {
            // Empfaenger des Bestellscheins (externer Dienstleister). Firma/Kontakt
            // werden ueber die Core-CRM-Interfaces aufgeloest (displayName/contacts);
            // Telefon gibt es dort nicht -> Freitext-Override.
            if (!Schema::hasColumn('events_order_items', 'crm_company_id')) {
                $table->unsignedBigInteger('crm_company_id')->nullable()->after('lieferant');
            }
            if (!Schema::hasColumn('events_order_items', 'crm_contact_id')) {
                $table->unsignedBigInteger('crm_contact_id')->nullable()->after('crm_company_id');
            }
            if (!Schema::hasColumn('events_order_items', 'empfaenger_tel')) {
                $table->string('empfaenger_tel')->nullable()->after('crm_contact_id');
            }
            if (!Schema::hasColumn('events_order_items', 'bemerkung')) {
                $table->text('bemerkung')->nullable()->after('empfaenger_tel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events_order_items', function (Blueprint $table) {
            foreach (['crm_company_id', 'crm_contact_id', 'empfaenger_tel', 'bemerkung'] as $col) {
                if (Schema::hasColumn('events_order_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
