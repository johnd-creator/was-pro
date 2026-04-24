<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faba_movements')) {
            return;
        }

        Schema::table('faba_movements', function (Blueprint $table) {
            $table->enum('approval_status', ['draft', 'pending_approval', 'approved', 'rejected'])
                ->default('approved')
                ->after('period_month');
            $table->uuid('submitted_by')->nullable()->after('approval_status');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->uuid('approved_by')->nullable()->after('submitted_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->uuid('rejected_by')->nullable()->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_note')->nullable()->after('rejected_at');

            $table->index('approval_status');
            $table->index('submitted_by');
            $table->index('approved_by');
            $table->index('rejected_by');
        });

        DB::table('faba_movements')
            ->whereNull('approval_status')
            ->update([
                'approval_status' => 'approved',
                'approved_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('faba_movements')) {
            return;
        }

        Schema::table('faba_movements', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
            $table->dropIndex(['submitted_by']);
            $table->dropIndex(['approved_by']);
            $table->dropIndex(['rejected_by']);

            $table->dropColumn([
                'approval_status',
                'submitted_by',
                'submitted_at',
                'approved_by',
                'approved_at',
                'rejected_by',
                'rejected_at',
                'rejection_note',
            ]);
        });
    }
};
