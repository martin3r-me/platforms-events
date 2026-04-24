<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            if (Schema::hasColumn('events_events', 'delivery_crm_contact_id')) {
                try { $table->dropIndex(['delivery_crm_contact_id']); } catch (\Throwable $e) {}
                $table->dropColumn('delivery_crm_contact_id');
            }
            if (Schema::hasColumn('events_events', 'delivery_contact')) {
                $table->dropColumn('delivery_contact');
            }
            if (Schema::hasColumn('events_events', 'delivery_crm_company_id')) {
                try { $table->dropIndex(['delivery_crm_company_id']); } catch (\Throwable $e) {}
                $table->dropColumn('delivery_crm_company_id');
            }
            if (Schema::hasColumn('events_events', 'delivery_supplier')) {
                $table->dropColumn('delivery_supplier');
            }

            if (!Schema::hasColumn('events_events', 'delivery_location_id')) {
                $table->unsignedBigInteger('delivery_location_id')->nullable()->after('delivery_address_crm_company_id');
                $table->index('delivery_location_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            if (Schema::hasColumn('events_events', 'delivery_location_id')) {
                try { $table->dropIndex(['delivery_location_id']); } catch (\Throwable $e) {}
                $table->dropColumn('delivery_location_id');
            }
            if (!Schema::hasColumn('events_events', 'delivery_supplier')) {
                $table->string('delivery_supplier')->nullable();
            }
            if (!Schema::hasColumn('events_events', 'delivery_crm_company_id')) {
                $table->unsignedBigInteger('delivery_crm_company_id')->nullable();
                $table->index('delivery_crm_company_id');
            }
            if (!Schema::hasColumn('events_events', 'delivery_contact')) {
                $table->string('delivery_contact')->nullable();
            }
            if (!Schema::hasColumn('events_events', 'delivery_crm_contact_id')) {
                $table->unsignedBigInteger('delivery_crm_contact_id')->nullable();
                $table->index('delivery_crm_contact_id');
            }
        });
    }
};
