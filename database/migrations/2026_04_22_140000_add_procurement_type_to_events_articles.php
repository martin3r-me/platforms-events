<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events_articles', function (Blueprint $table) {
            // 'stock'    - Eigener Lagerbestand, kommt in die Packliste
            // 'supplier' - Wird bei Dienstleistern extern bestellt
            // 'kitchen'  - Wird selbst hergestellt (Speisen), kommt in die Projekt-Function
            $table->string('procurement_type', 16)->default('stock')->after('is_active');
            $table->index('procurement_type', 'idx_articles_procurement_type');
        });
    }

    public function down(): void
    {
        Schema::table('events_articles', function (Blueprint $table) {
            $table->dropIndex('idx_articles_procurement_type');
            $table->dropColumn('procurement_type');
        });
    }
};
