<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_location_pricing_applications')) {
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
                $table->index(['quote_item_id', 'location_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_location_pricing_applications');
    }
};
