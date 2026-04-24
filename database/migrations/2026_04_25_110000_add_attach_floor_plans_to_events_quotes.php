<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('events_quotes', 'attach_floor_plans')) {
                // null = Team-Default aus SettingsService verwenden
                // true/false = Projektleiter-Override fuer dieses Angebot
                $table->boolean('attach_floor_plans')->nullable()->after('pdf_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events_quotes', function (Blueprint $table) {
            if (Schema::hasColumn('events_quotes', 'attach_floor_plans')) {
                $table->dropColumn('attach_floor_plans');
            }
        });
    }
};
