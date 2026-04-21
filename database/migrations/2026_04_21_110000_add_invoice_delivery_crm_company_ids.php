<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_crm_company_id')->nullable()->after('invoice_to')
                ->comment('FK → crm_companies.id für „Rechnung an" (Contract-gebunden)');
            $table->unsignedBigInteger('delivery_crm_company_id')->nullable()->after('delivery_supplier')
                ->comment('FK → crm_companies.id für „Lieferung an" (Contract-gebunden)');
            $table->index('invoice_crm_company_id');
            $table->index('delivery_crm_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->dropIndex(['invoice_crm_company_id']);
            $table->dropIndex(['delivery_crm_company_id']);
            $table->dropColumn(['invoice_crm_company_id', 'delivery_crm_company_id']);
        });
    }
};
