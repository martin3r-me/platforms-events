<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotenter Neuaufbau: falls ein partieller Stand aus vorherigem
        // Fehlschlag existiert (applications-Tabelle angelegt ohne FK), wird
        // alles sauber abgeraeumt. Reihenfolge: erst Applications (weil sie
        // auf Rules verweist), dann Rules.
        Schema::dropIfExists('events_flat_rate_applications');
        Schema::dropIfExists('events_flat_rate_rules');

        Schema::create('events_flat_rate_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Scope: auf welche Vorgangs-Typen (QuoteItem.typ) greift die Regel?
            $table->json('scope_typs');
            $table->json('scope_event_types')->nullable();

            // Expression-Body
            $table->longText('formula');

            // Output-Konfiguration fuer die erzeugte QuotePosition
            $table->string('output_name');
            $table->string('output_gruppe');
            $table->string('output_mwst', 10)->default('19%');
            $table->string('output_procurement_type', 40)->nullable();

            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);

            // Laufende Fehler-Anzeige
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('team_id');
            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'priority']);
        });

        Schema::create('events_flat_rate_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('events_flat_rate_rules')->nullOnDelete();
            $table->foreignId('quote_item_id')->constrained('events_quote_items')->cascadeOnDelete();
            // Hinweis: Die Positions-Tabelle heisst mit deutscher Pluralform.
            $table->foreignId('quote_position_id')->nullable()->constrained('events_quote_positionen')->nullOnDelete();

            $table->json('input_snapshot');
            $table->decimal('result_value', 12, 2)->default(0);
            $table->json('result_breakdown')->nullable();

            $table->timestamp('superseded_at')->nullable();

            $table->timestamps();

            $table->index(['quote_item_id', 'superseded_at']);
            $table->index(['rule_id', 'quote_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events_flat_rate_applications');
        Schema::dropIfExists('events_flat_rate_rules');
    }
};
