<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_quotes')) {
            Schema::create('events_quotes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->constrained('events_events')->cascadeOnDelete();

                $table->string('token', 64)->unique();
                $table->string('status', 30)->default('draft'); // draft|sent|accepted|rejected
                $table->date('valid_until')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->text('response_note')->nullable();
                $table->timestamp('last_viewed_at')->nullable();
                $table->unsignedInteger('view_count')->default(0);

                // Versionierung
                $table->unsignedInteger('version')->default(1);
                $table->foreignId('parent_id')->nullable()->constrained('events_quotes')->nullOnDelete();
                $table->boolean('is_current')->default(true);
                $table->longText('pdf_snapshot')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index('event_id');
                $table->index('team_id');
                $table->index(['event_id', 'is_current']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_quotes');
    }
};
