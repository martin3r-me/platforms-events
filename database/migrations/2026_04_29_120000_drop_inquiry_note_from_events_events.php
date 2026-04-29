<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            if (Schema::hasColumn('events_events', 'inquiry_note')) {
                $table->dropColumn('inquiry_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            if (!Schema::hasColumn('events_events', 'inquiry_note')) {
                $table->string('inquiry_note')->nullable()->after('inquiry_time');
            }
        });
    }
};
