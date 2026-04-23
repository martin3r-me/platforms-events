<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            // Lieferadresse: eigener CRM-Company-Slot innerhalb der Lieferung-Sektion
            $table->unsignedBigInteger('delivery_address_crm_company_id')->nullable()->after('delivery_crm_contact_id');
            $table->string('delivery_address')->nullable()->after('delivery_address_crm_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->dropColumn(['delivery_address_crm_company_id', 'delivery_address']);
        });
    }
};
