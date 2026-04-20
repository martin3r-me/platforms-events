<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_order_positionen')) {
            Schema::create('events_order_positionen', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('order_item_id')->constrained('events_order_items')->cascadeOnDelete();

                $table->string('gruppe', 255)->default('');
                $table->string('name', 500)->default('');
                $table->string('anz', 20)->default('');
                $table->string('anz2', 20)->default('');
                $table->string('uhrzeit', 10)->default('');
                $table->string('bis', 10)->default('');
                $table->string('inhalt', 50)->default('');
                $table->string('gebinde', 80)->default('');
                $table->decimal('basis_ek', 10, 2)->default(0);
                $table->decimal('ek', 10, 2)->default(0);
                $table->decimal('preis', 10, 2)->default(0);
                $table->string('mwst', 5)->default('7%');
                $table->decimal('gesamt', 10, 2)->default(0);
                $table->string('bemerkung', 500)->default('');
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('order_item_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_order_positionen');
    }
};
