<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_email_log')) {
            Schema::create('events_email_log', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->nullable()->constrained('events_events')->nullOnDelete();

                $table->string('type', 30); // quote, invoice, contract, reminder, custom
                $table->string('to');
                $table->string('cc')->nullable();
                $table->string('subject');
                $table->text('body')->nullable();
                $table->string('attachment_name')->nullable();
                $table->string('status', 20)->default('sent'); // sent|failed|opened
                $table->string('sent_by')->nullable();
                $table->string('tracking_token', 48)->nullable()->unique();
                $table->timestamp('opened_at')->nullable();

                $table->timestamps();

                $table->index(['event_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_email_log');
    }
};
