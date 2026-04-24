<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_event_days', function (Blueprint $table) {
            if (!Schema::hasColumn('events_event_days', 'split_a')) {
                $table->unsignedTinyInteger('split_a')->default(50)->after('pers_bis');
            }
            if (!Schema::hasColumn('events_event_days', 'children_count')) {
                $table->unsignedSmallInteger('children_count')->nullable()->after('split_a');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events_event_days', function (Blueprint $table) {
            foreach (['split_a', 'children_count'] as $col) {
                if (Schema::hasColumn('events_event_days', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
