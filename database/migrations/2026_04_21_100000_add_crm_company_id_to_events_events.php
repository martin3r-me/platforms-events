<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->unsignedBigInteger('crm_company_id')->nullable()->after('customer')
                ->comment('FK → crm_companies.id (lose, Cross-Module via CrmCompanyResolverInterface)');
            $table->index('crm_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->dropIndex(['crm_company_id']);
            $table->dropColumn('crm_company_id');
        });
    }
};
