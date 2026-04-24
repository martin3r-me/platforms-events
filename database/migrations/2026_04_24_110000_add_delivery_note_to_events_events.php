<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            if (!Schema::hasColumn('events_events', 'delivery_note')) {
                $table->string('delivery_note', 255)->nullable()->after('delivery_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            if (Schema::hasColumn('events_events', 'delivery_note')) {
                $table->dropColumn('delivery_note');
            }
        });
    }
};
