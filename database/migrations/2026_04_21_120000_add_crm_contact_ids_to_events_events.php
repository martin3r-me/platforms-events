<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->unsignedBigInteger('organizer_crm_contact_id')->nullable()->after('organizer_contact')
                ->comment('FK → crm_contacts.id (Contract-gebunden, gehoert zu crm_company_id)');
            $table->unsignedBigInteger('organizer_onsite_crm_contact_id')->nullable()->after('organizer_contact_onsite')
                ->comment('FK → crm_contacts.id fuer Ansprechpartner vor Ort');
            $table->unsignedBigInteger('invoice_crm_contact_id')->nullable()->after('invoice_contact')
                ->comment('FK → crm_contacts.id fuer Rechnungs-Ansprechpartner (Company: invoice_crm_company_id)');
            $table->unsignedBigInteger('delivery_crm_contact_id')->nullable()->after('delivery_contact')
                ->comment('FK → crm_contacts.id fuer Liefer-Ansprechpartner (Company: delivery_crm_company_id)');

            $table->index('organizer_crm_contact_id');
            $table->index('organizer_onsite_crm_contact_id');
            $table->index('invoice_crm_contact_id');
            $table->index('delivery_crm_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('events_events', function (Blueprint $table) {
            $table->dropIndex(['organizer_crm_contact_id']);
            $table->dropIndex(['organizer_onsite_crm_contact_id']);
            $table->dropIndex(['invoice_crm_contact_id']);
            $table->dropIndex(['delivery_crm_contact_id']);
            $table->dropColumn([
                'organizer_crm_contact_id',
                'organizer_onsite_crm_contact_id',
                'invoice_crm_contact_id',
                'delivery_crm_contact_id',
            ]);
        });
    }
};
