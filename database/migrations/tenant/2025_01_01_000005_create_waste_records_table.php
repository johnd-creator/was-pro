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
        Schema::create('waste_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('record_number')->unique();
            $table->date('date');
            $table->uuid('waste_type_id');
            $table->decimal('quantity', 12, 2);
            $table->string('unit')->default('kg');
            $table->string('source')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            // Workflow fields
            $table->enum('status', ['draft', 'pending_review', 'approved', 'rejected'])->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Audit trail
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('waste_type_id')->references('id')->on('waste_types')->onDelete('restrict');

            $table->index('record_number');
            $table->index('date');
            $table->index('status');
            $table->index('submitted_by');
            $table->index('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_records');
    }
};
