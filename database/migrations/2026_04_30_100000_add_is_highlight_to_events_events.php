<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_events')) {
            return;
        }
        if (Schema::hasColumn('events_events', 'is_highlight')) {
            return;
        }

        Schema::table('events_events', function (Blueprint $table) {
            // Veranstaltungen, die als „besonders sehenswert" markiert sind
            // (Foto-Termin, vor Ort sein, internes Highlight). Default false.
            $table->boolean('is_highlight')->default(false)->after('status_changed_at');
        });

        // Index separat anlegen mit explizitem Namen (vermeidet Probleme mit
        // Laravels Auto-Naming + SQLite-Constraint-Check).
        Schema::table('events_events', function (Blueprint $table) {
            $table->index(['team_id', 'is_highlight'], 'events_events_team_highlight_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('events_events') || !Schema::hasColumn('events_events', 'is_highlight')) {
            return;
        }
        Schema::table('events_events', function (Blueprint $table) {
            $table->dropIndex('events_events_team_highlight_idx');
            $table->dropColumn('is_highlight');
        });
    }
};
