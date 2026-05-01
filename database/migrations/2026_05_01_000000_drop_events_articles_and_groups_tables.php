<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove article_group_id FK from packages (must happen before dropping groups table)
        if (Schema::hasColumn('events_article_packages', 'article_group_id')) {
            Schema::table('events_article_packages', function (Blueprint $table) {
                // Drop FK constraint if exists
                try {
                    $table->dropForeign(['article_group_id']);
                } catch (\Throwable $e) {
                    // Constraint might not exist
                }
                $table->dropColumn('article_group_id');
            });
        }

        // Articles depend on groups, drop articles first
        Schema::dropIfExists('events_articles');
        Schema::dropIfExists('events_article_groups');
    }

    public function down(): void
    {
        // Re-create article_groups
        Schema::create('events_article_groups', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('events_article_groups')->nullOnDelete();
            $table->string('name', 100);
            $table->string('color', 20)->nullable();
            $table->string('erloeskonto_7', 20)->default('8300');
            $table->string('erloeskonto_19', 20)->default('8400');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Re-create articles
        Schema::create('events_articles', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_group_id')->nullable()->constrained('events_article_groups')->nullOnDelete();
            $table->string('article_number', 30)->nullable();
            $table->string('external_code', 100)->nullable();
            $table->string('name', 500);
            $table->text('description')->nullable();
            $table->text('offer_text')->nullable();
            $table->text('invoice_text')->nullable();
            $table->string('gebinde', 80)->nullable();
            $table->decimal('ek', 10, 2)->default(0);
            $table->decimal('vk', 10, 2)->default(0);
            $table->string('mwst', 5)->default('19%');
            $table->string('erloeskonto', 20)->nullable();
            $table->string('lagerort', 100)->nullable();
            $table->integer('min_bestand')->default(0);
            $table->integer('current_bestand')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('procurement_type', 20)->default('stock');
            $table->timestamps();
            $table->softDeletes();
        });

        // Re-add article_group_id to packages
        Schema::table('events_article_packages', function (Blueprint $table) {
            $table->foreignId('article_group_id')->nullable()->after('team_id')
                ->constrained('events_article_groups')->nullOnDelete();
        });
    }
};
