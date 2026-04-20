<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_contracts')) {
            Schema::create('events_contracts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->constrained('events_events')->cascadeOnDelete();

                $table->string('type', 30)->default('nutzungsvertrag'); // nutzungsvertrag | optionsbestaetigung | ...
                $table->string('token', 64)->unique();
                $table->string('status', 30)->default('draft'); // draft|sent|signed|rejected
                $table->json('content')->nullable();

                $table->timestamp('sent_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('last_viewed_at')->nullable();
                $table->unsignedInteger('view_count')->default(0);

                // Versionierung
                $table->unsignedInteger('version')->default(1);
                $table->foreignId('parent_id')->nullable()->constrained('events_contracts')->nullOnDelete();
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
        Schema::dropIfExists('events_contracts');
    }
};
