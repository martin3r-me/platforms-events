<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_app_notifications')) {
            Schema::create('events_app_notifications', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                $table->string('type', 50); // event_status, invoice_overdue, quote_response, contract_signed, assignment, picklist_done
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('link')->nullable();
                $table->string('icon', 30)->default('info');
                $table->timestamp('read_at')->nullable();

                $table->timestamps();

                $table->index(['user_id', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_app_notifications');
    }
};
