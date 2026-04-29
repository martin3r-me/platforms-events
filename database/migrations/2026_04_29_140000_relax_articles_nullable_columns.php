<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * lagerort und gebinde duerfen NULL sein. Beide hatten bisher Default '' und
 * NOT NULL – fuer Mietartikel/Hallen ohne Lagerbestand fachlich unsinnig.
 * Bestehende Daten mit '' bleiben erhalten; null wird ab jetzt akzeptiert.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('events_articles')) {
            Schema::table('events_articles', function (Blueprint $table) {
                $table->string('gebinde', 80)->nullable()->default(null)->change();
                $table->string('lagerort', 100)->nullable()->default(null)->change();
            });
            // Optional: bestehende '' auf NULL normalisieren (sauberer fuer Reports).
            DB::table('events_articles')->where('gebinde', '')->update(['gebinde' => null]);
            DB::table('events_articles')->where('lagerort', '')->update(['lagerort' => null]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events_articles')) {
            // Vor Rollback NULLs zurueck auf '' setzen, damit der Constraint wieder greift.
            DB::table('events_articles')->whereNull('gebinde')->update(['gebinde' => '']);
            DB::table('events_articles')->whereNull('lagerort')->update(['lagerort' => '']);
            Schema::table('events_articles', function (Blueprint $table) {
                $table->string('gebinde', 80)->default('')->change();
                $table->string('lagerort', 100)->default('')->change();
            });
        }
    }
};
