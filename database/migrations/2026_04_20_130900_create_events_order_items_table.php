<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_order_items')) {
            Schema::create('events_order_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_day_id')->constrained('events_event_days')->cascadeOnDelete();

                $table->string('typ');
                $table->string('status')->default('Offen');
                $table->unsignedSmallInteger('artikel')->default(0);
                $table->unsignedSmallInteger('positionen')->default(0);
                $table->decimal('einkauf', 10, 2)->default(0);
                $table->string('lieferant')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('event_day_id');
                $table->index('team_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_order_items');
    }
};
