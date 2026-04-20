<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_document_signatures')) {
            Schema::create('events_document_signatures', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->constrained('events_events')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                $table->string('role'); // 'left' | 'right'
                $table->longText('signature_image'); // base64 PNG
                $table->string('document_hash', 64); // SHA-256 snapshot
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamp('signed_at');

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['event_id', 'role']);
                $table->index('team_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_document_signatures');
    }
};
