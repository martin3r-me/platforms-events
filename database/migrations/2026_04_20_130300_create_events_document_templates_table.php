<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_document_templates')) {
            Schema::create('events_document_templates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();

                $table->string('label');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('color', 20)->default('#7c3aed');
                $table->json('placeholders')->nullable();
                $table->longText('html_content')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['team_id', 'slug']);
                $table->index('team_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_document_templates');
    }
};
