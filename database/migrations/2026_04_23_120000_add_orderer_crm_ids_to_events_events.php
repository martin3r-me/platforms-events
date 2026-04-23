<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->unsignedBigInteger('orderer_crm_company_id')->nullable()->after('orderer_via');
            $table->unsignedBigInteger('orderer_crm_contact_id')->nullable()->after('orderer_crm_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->dropColumn(['orderer_crm_company_id', 'orderer_crm_contact_id']);
        });
    }
};
