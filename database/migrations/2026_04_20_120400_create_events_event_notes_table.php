<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_event_notes')) {
            Schema::create('events_event_notes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->constrained('events_events')->cascadeOnDelete();

                $table->string('type'); // liefertext | absprache | vereinbarung
                $table->text('text');
                $table->string('user_name')->default('Benutzer');

                $table->timestamps();
                $table->softDeletes();

                $table->index('event_id');
                $table->index('team_id');
                $table->index(['event_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_event_notes');
    }
};
