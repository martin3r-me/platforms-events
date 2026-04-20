<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events_events')) {
            Schema::create('events_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();

                // Kern
                $table->string('event_number', 30)->unique();
                $table->string('name');
                $table->string('customer')->nullable();
                $table->string('group')->nullable();
                $table->string('location')->nullable();

                // Zeitraum
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();

                // Status
                $table->string('status')->nullable()->default('Option');
                $table->timestamp('status_changed_at')->nullable();

                // Veranstalter
                $table->string('organizer_contact')->nullable();
                $table->string('organizer_contact_onsite')->nullable();
                $table->string('organizer_for_whom')->nullable();

                // Besteller
                $table->string('orderer_company')->nullable();
                $table->string('orderer_contact')->nullable();
                $table->string('orderer_via')->default('mail');

                // Rechnung
                $table->string('invoice_to')->nullable();
                $table->string('invoice_contact')->nullable();
                $table->string('invoice_date_type')->nullable();

                // Zuständigkeit
                $table->string('responsible')->nullable();
                $table->string('cost_center')->nullable();
                $table->string('cost_carrier')->nullable();

                // Anlass
                $table->string('event_type')->nullable();

                // Unterschriften
                $table->string('sign_left')->nullable();
                $table->string('sign_right')->nullable();

                // Management Report (freies JSON – Feld-Definitionen folgen später)
                $table->json('mr_data')->nullable();

                // Wiedervorlage
                $table->date('follow_up_date')->nullable();
                $table->text('follow_up_note')->nullable();

                // Lieferung
                $table->string('delivery_supplier')->nullable();
                $table->string('delivery_contact')->nullable();

                // Eingang (Anfrage)
                $table->date('inquiry_date')->nullable();
                $table->string('inquiry_time')->nullable();
                $table->string('inquiry_note')->nullable();
                $table->string('potential')->nullable();

                // Weiterleitung
                $table->boolean('forwarded')->default(false);
                $table->date('forwarding_date')->nullable();
                $table->string('forwarding_time')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index('team_id');
                $table->index('start_date');
                $table->index('end_date');
                $table->index('status');
                $table->index(['team_id', 'start_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events_events');
    }
};
