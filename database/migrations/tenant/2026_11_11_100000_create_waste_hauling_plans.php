<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_hauling_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('waste_record_id')->constrained('waste_records')->restrictOnDelete();
            $table->date('planned_date');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 20);
            $table->text('notes')->nullable();
            $table->string('status')->default('pending_approval')->index();
            $table->uuid('created_by');
            $table->uuid('submitted_by');
            $table->timestamp('submitted_at');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('waste_haulings', function (Blueprint $table): void {
            $table->foreignUuid('waste_hauling_plan_id')->nullable()->constrained('waste_hauling_plans')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        app(\App\Services\OperationalTransactionService::class)->run(function (): void {
            if (\App\Models\WasteHaulingPlan::withTrashed()->exists()) {
                throw new RuntimeException('Rencana angkut sudah digunakan; rollback akan menghilangkan komitmen dan riwayat.');
            }
            Schema::table('waste_haulings', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('waste_hauling_plan_id');
            });
            Schema::dropIfExists('waste_hauling_plans');
        });
    }
};
