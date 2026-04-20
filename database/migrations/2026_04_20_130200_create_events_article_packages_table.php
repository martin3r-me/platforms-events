<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_article_packages')) {
            Schema::create('events_article_packages', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('article_group_id')->nullable()->constrained('events_article_groups')->nullOnDelete();

                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->string('color', 20)->default('#8b5cf6');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('team_id');
            });
        }

        if (!Schema::hasTable('events_article_package_items')) {
            Schema::create('events_article_package_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('package_id')->constrained('events_article_packages')->cascadeOnDelete();
                $table->foreignId('article_id')->nullable()->constrained('events_articles')->nullOnDelete();

                $table->string('name', 500)->default('');
                $table->string('gruppe', 255)->default('');
                $table->integer('quantity')->default(1);
                $table->string('gebinde', 80)->default('');
                $table->decimal('vk', 10, 2)->default(0);
                $table->decimal('gesamt', 10, 2)->default(0);
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('package_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_article_package_items');
        Schema::dropIfExists('events_article_packages');
    }
};
