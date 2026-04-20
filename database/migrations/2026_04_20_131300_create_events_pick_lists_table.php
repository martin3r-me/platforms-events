<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_pick_lists')) {
            Schema::create('events_pick_lists', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->nullable()->constrained('events_events')->nullOnDelete();

                $table->string('title', 255);
                $table->string('status', 30)->default('open'); // open|in_progress|packed|loaded
                $table->string('token', 48)->unique();
                $table->string('created_by', 100)->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index('event_id');
                $table->index('team_id');
            });
        }

        if (!Schema::hasTable('events_pick_items')) {
            Schema::create('events_pick_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('pick_list_id')->constrained('events_pick_lists')->cascadeOnDelete();
                $table->foreignId('article_id')->nullable()->constrained('events_articles')->nullOnDelete();

                $table->string('name', 500);
                $table->integer('quantity')->default(1);
                $table->string('gebinde', 80)->default('');
                $table->string('gruppe', 255)->default('');
                $table->string('lagerort', 100)->default('');
                $table->string('status', 30)->default('open'); // open|picked|packed|loaded
                $table->string('picked_by', 100)->nullable();
                $table->timestamp('picked_at')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('pick_list_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_pick_items');
        Schema::dropIfExists('events_pick_lists');
    }
};
