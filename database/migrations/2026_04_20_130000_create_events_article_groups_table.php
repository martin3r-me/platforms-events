<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_article_groups')) {
            Schema::create('events_article_groups', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('events_article_groups')->nullOnDelete();

                $table->string('name', 100);
                $table->string('color', 20)->default('#6366f1');
                $table->string('erloeskonto_7', 20)->default('8300');
                $table->string('erloeskonto_19', 20)->default('8400');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);

                $table->timestamps();
                $table->softDeletes();

                $table->index('team_id');
                $table->index('parent_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_article_groups');
    }
};
