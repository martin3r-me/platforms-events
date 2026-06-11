<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('events_bookings', 'option_until')) {
            return;
        }

        Schema::table('events_bookings', function (Blueprint $table) {
            // Optionsfrist: bis wann gilt die Raum-Option? Danach gehoert sie
            // verlaengert, hochgestuft oder freigegeben (Wiedervorlage-Cockpit).
            $table->date('option_until')->nullable()->after('optionsrang');
            $table->index(['team_id', 'option_until']);
        });
    }

    public function down(): void
    {
        Schema::table('events_bookings', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'option_until']);
            $table->dropColumn('option_until');
        });
    }
};
