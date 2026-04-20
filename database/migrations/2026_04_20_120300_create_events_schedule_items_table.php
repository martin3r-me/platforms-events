<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_schedule_items')) {
            Schema::create('events_schedule_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->constrained('events_events')->cascadeOnDelete();

                $table->string('datum', 10)->nullable();
                $table->string('von', 10)->nullable();
                $table->string('bis', 10)->nullable();
                $table->string('beschreibung');
                $table->string('raum')->nullable();
                $table->string('bemerkung')->nullable();
                $table->boolean('linked')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('event_id');
                $table->index('team_id');
                $table->index(['event_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_schedule_items');
    }
};
