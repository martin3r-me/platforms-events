<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_event_days', function (Blueprint $table) {
            if (!Schema::hasColumn('events_event_days', 'day_type')) {
                $table->string('day_type', 40)->default('Veranstaltungstag')->after('label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events_event_days', function (Blueprint $table) {
            if (Schema::hasColumn('events_event_days', 'day_type')) {
                $table->dropColumn('day_type');
            }
        });
    }
};
