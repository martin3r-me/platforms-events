<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->string('internal_rating', 64)->nullable()->after('mr_data');
            $table->string('customer_satisfaction', 64)->nullable()->after('internal_rating');
            $table->string('rebooking_recommendation', 64)->nullable()->after('customer_satisfaction');
        });
    }

    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->dropColumn(['internal_rating', 'customer_satisfaction', 'rebooking_recommendation']);
        });
    }
};
