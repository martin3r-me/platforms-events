<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_invoices')) {
            Schema::create('events_invoices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('event_id')->nullable()->constrained('events_events')->nullOnDelete();
                $table->foreignId('quote_id')->nullable()->constrained('events_quotes')->nullOnDelete();

                $table->string('invoice_number', 30);
                $table->string('type', 30)->default('rechnung'); // rechnung|teilrechnung|schlussrechnung|gutschrift|storno
                $table->string('status', 30)->default('draft'); // draft|sent|paid|overdue|cancelled

                // Customer snapshot
                $table->string('customer_company', 255)->default('');
                $table->string('customer_contact', 255)->default('');
                $table->text('customer_address')->nullable();
                $table->string('customer_city', 255)->default('');

                // Dates
                $table->date('invoice_date')->nullable();
                $table->date('due_date')->nullable();
                $table->date('payment_date')->nullable();
                $table->string('payment_reference', 255)->default('');

                // Amounts
                $table->decimal('netto', 12, 2)->default(0);
                $table->decimal('mwst_7', 12, 2)->default(0);
                $table->decimal('mwst_19', 12, 2)->default(0);
                $table->decimal('brutto', 12, 2)->default(0);

                // Texts
                $table->text('notes')->nullable();
                $table->text('internal_notes')->nullable();

                // Accounting
                $table->string('cost_center', 50)->default('');
                $table->string('cost_carrier', 50)->default('');

                // Relations
                $table->foreignId('related_invoice_id')->nullable()->constrained('events_invoices')->nullOnDelete();

                // PDF + Token
                $table->longText('pdf_snapshot')->nullable();
                $table->string('token', 48)->unique();

                // Versioning
                $table->unsignedInteger('version')->default(1);
                $table->foreignId('parent_id')->nullable()->constrained('events_invoices')->nullOnDelete();
                $table->boolean('is_current')->default(true);

                // Public view tracking
                $table->unsignedInteger('view_count')->default(0);
                $table->timestamp('last_viewed_at')->nullable();

                // Tracking
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('reminded_at')->nullable();
                $table->unsignedSmallInteger('reminder_level')->default(0);
                $table->string('created_by', 100)->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['team_id', 'invoice_number']);
                $table->index('event_id');
                $table->index('team_id');
                $table->index(['event_id', 'is_current']);
            });
        }

        if (!Schema::hasTable('events_invoice_items')) {
            Schema::create('events_invoice_items', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignId('invoice_id')->constrained('events_invoices')->cascadeOnDelete();
                $table->foreignId('article_id')->nullable()->constrained('events_articles')->nullOnDelete();

                $table->string('gruppe', 100)->default('');
                $table->string('name', 500);
                $table->text('description')->nullable();
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('quantity2', 10, 2)->default(0);
                $table->string('gebinde', 80)->default('');
                $table->decimal('unit_price', 10, 2)->default(0);
                $table->unsignedSmallInteger('mwst_rate')->default(19);
                $table->decimal('total', 12, 2)->default(0);
                $table->unsignedSmallInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->index('invoice_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_invoice_items');
        Schema::dropIfExists('events_invoices');
    }
};
