<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_audit_log')) {
            Schema::create('events_audit_log', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();

                $table->string('auditable_type', 120);
                $table->unsignedBigInteger('auditable_id');
                $table->foreignId('event_id')->nullable()->constrained('events_events')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name', 100)->nullable();
                $table->string('action', 20); // created|updated|deleted
                $table->json('changes')->nullable();
                $table->timestamp('created_at');

                $table->index(['auditable_type', 'auditable_id']);
                $table->index(['event_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_audit_log');
    }
};
