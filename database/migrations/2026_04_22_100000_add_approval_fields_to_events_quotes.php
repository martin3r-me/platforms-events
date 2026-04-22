<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events_quotes', function (Blueprint $table) {
            // 'none' | 'pending' | 'approved' | 'rejected'
            $table->string('approval_status', 16)->default('none')->after('status');
            $table->unsignedBigInteger('approver_id')->nullable()->after('approval_status');
            $table->unsignedBigInteger('approval_requested_by')->nullable()->after('approver_id');
            $table->timestamp('approval_requested_at')->nullable()->after('approval_requested_by');
            $table->timestamp('approval_decided_at')->nullable()->after('approval_requested_at');
            $table->text('approval_comment')->nullable()->after('approval_decided_at');

            $table->index(['approver_id', 'approval_status'], 'idx_quotes_approver_status');
        });
    }

    public function down(): void
    {
        Schema::table('events_quotes', function (Blueprint $table) {
            $table->dropIndex('idx_quotes_approver_status');
            $table->dropColumn([
                'approval_status',
                'approver_id',
                'approval_requested_by',
                'approval_requested_at',
                'approval_decided_at',
                'approval_comment',
            ]);
        });
    }
};
