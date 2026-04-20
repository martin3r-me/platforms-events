<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_feedback_links')) {
            Schema::create('events_feedback_links', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->constrained('events_events')->cascadeOnDelete();

                $table->string('label');
                $table->string('audience', 30); // participant|client|vendor|other
                $table->string('token', 48)->unique();
                $table->unsignedInteger('view_count')->default(0);
                $table->timestamp('last_viewed_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->timestamps();
                $table->softDeletes();

                $table->index('event_id');
                $table->index('team_id');
            });
        }

        if (!Schema::hasTable('events_feedback_entries')) {
            Schema::create('events_feedback_entries', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('feedback_link_id')->constrained('events_feedback_links')->cascadeOnDelete();
                $table->foreignId('event_id')->constrained('events_events')->cascadeOnDelete();

                $table->string('name')->nullable();
                $table->unsignedTinyInteger('rating_overall')->nullable();
                $table->unsignedTinyInteger('rating_location')->nullable();
                $table->unsignedTinyInteger('rating_catering')->nullable();
                $table->unsignedTinyInteger('rating_organization')->nullable();
                $table->text('comment')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();

                $table->timestamps();

                $table->index(['event_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_feedback_entries');
        Schema::dropIfExists('events_feedback_links');
    }
};
