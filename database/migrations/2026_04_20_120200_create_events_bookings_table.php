<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_bookings')) {
            Schema::create('events_bookings', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->constrained('events_events')->cascadeOnDelete();

                // Location-Referenz bevorzugt, raum-String als Fallback für Altdaten
                $table->foreignId('location_id')->nullable()->constrained('locations_locations')->nullOnDelete();
                $table->string('raum')->nullable();

                $table->string('datum')->nullable();
                $table->string('beginn', 10)->nullable();
                $table->string('ende', 10)->nullable();
                $table->string('pers', 20)->nullable();
                $table->string('bestuhlung')->nullable();
                $table->string('optionsrang', 20)->default('1. Option');
                $table->string('absprache')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('event_id');
                $table->index('location_id');
                $table->index('team_id');
                $table->index(['event_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_bookings');
    }
};
