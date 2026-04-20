<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_mr_field_configs')) {
            Schema::create('events_mr_field_configs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();

                $table->string('group_label');
                $table->string('label');
                $table->json('options');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);

                $table->timestamps();
                $table->softDeletes();

                $table->index('team_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_mr_field_configs');
    }
};
