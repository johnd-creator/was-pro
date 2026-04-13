<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('waste_haulings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('hauling_number')->unique();
            $table->uuid('waste_record_id');
            $table->date('hauling_date');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 20);
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'cancelled'])->default('pending_approval');
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('waste_record_id')->references('id')->on('waste_records')->onDelete('restrict');

            $table->index('hauling_number');
            $table->index('hauling_date');
            $table->index('status');
            $table->index('waste_record_id');
            $table->index('submitted_by');
            $table->index('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_haulings');
    }
};
