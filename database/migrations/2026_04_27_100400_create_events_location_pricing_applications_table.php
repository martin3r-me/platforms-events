<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recovery-Pfad: Falls eine vorherige Variante dieser Migration mit
        // einem zu langen Auto-Index-Namen
        // (events_location_pricing_applications_quote_item_id_location_id_index, 68 Zeichen,
        //  MySQL-Limit 64) gefehlt ist, ist die Tabelle bereits angelegt aber
        // ohne Composite-Index. Den fehlenden Index mit kurzem Namen nachziehen.
        if (Schema::hasTable('events_location_pricing_applications')) {
            $hasComposite = collect(DB::select("SHOW INDEX FROM events_location_pricing_applications"))
                ->pluck('Key_name')
                ->contains('events_lpa_qi_loc_idx');

            if (!$hasComposite) {
                Schema::table('events_location_pricing_applications', function (Blueprint $table) {
                    $table->index(['quote_item_id', 'location_id'], 'events_lpa_qi_loc_idx');
                });
            }
            return;
        }

        Schema::create('events_location_pricing_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('quote_item_id')->constrained('events_quote_items')->cascadeOnDelete();
            // FK auf locations_locations (Cross-Modul); nullable, falls Location spaeter geloescht wird
            $table->foreignId('location_id')->nullable()->constrained('locations_locations')->nullOnDelete();

            // Schnappschuesse
            $table->json('input_snapshot');     // EventDay.day_type, gewaehlte Pricing-/Addon-IDs, Mengen, Warnings
            $table->json('created_positions')->nullable(); // [{quote_position_id, source: pricing|addon, ref_id}, ...]

            $table->timestamp('superseded_at')->nullable(); // Soft-Overwrite fuer Re-Apply

            $table->timestamps();
            $table->softDeletes();

            $table->index('quote_item_id');
            $table->index('location_id');
            // Expliziter, kurzer Name — Auto-Generated waere mit dem langen
            // Tabellennamen (36 Zeichen) ueber dem MySQL-64-Zeichen-Limit.
            $table->index(['quote_item_id', 'location_id'], 'events_lpa_qi_loc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events_location_pricing_applications');
    }
};
