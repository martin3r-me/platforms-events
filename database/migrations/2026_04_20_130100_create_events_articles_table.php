<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_articles')) {
            Schema::create('events_articles', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('article_group_id')->nullable()->constrained('events_article_groups')->nullOnDelete();

                $table->string('article_number', 30);
                $table->string('external_code', 100)->nullable();
                $table->string('name', 500);
                $table->text('description')->nullable();
                $table->text('offer_text')->nullable();
                $table->text('invoice_text')->nullable();
                $table->string('gebinde', 80)->default('');
                $table->decimal('ek', 10, 2)->default(0);
                $table->decimal('vk', 10, 2)->default(0);
                $table->string('mwst', 5)->default('19%');
                $table->string('erloeskonto', 20)->nullable();
                $table->string('lagerort', 100)->default('');
                $table->integer('min_bestand')->default(0);
                $table->integer('current_bestand')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['team_id', 'article_number']);
                $table->index(['team_id', 'is_active', 'name']);
                $table->index('article_number');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_articles');
    }
};
