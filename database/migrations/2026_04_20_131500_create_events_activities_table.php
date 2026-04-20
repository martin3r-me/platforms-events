<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_activities')) {
            Schema::create('events_activities', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->constrained('events_events')->cascadeOnDelete();

                $table->string('type', 50);
                $table->string('description');
                $table->string('user')->nullable(); // legacy user-name string

                $table->timestamps();

                $table->index('event_id');
                $table->index(['event_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_activities');
    }
};
