<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_settings')) {
            Schema::create('events_settings', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();

                $table->string('key');
                $table->longText('value')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['team_id', 'key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_settings');
    }
};
