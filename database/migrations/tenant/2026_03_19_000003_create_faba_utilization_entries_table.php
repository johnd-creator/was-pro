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
        Schema::create('faba_utilization_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entry_number')->unique();
            $table->date('transaction_date');
            $table->enum('material_type', ['fly_ash', 'bottom_ash']);
            $table->enum('utilization_type', ['external', 'internal']);
            $table->uuid('vendor_id')->nullable();
            $table->decimal('quantity', 15, 2);
            $table->string('unit')->default('ton');
            $table->string('document_number')->nullable();
            $table->date('document_date')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->index('transaction_date');
            $table->index('material_type');
            $table->index('utilization_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faba_utilization_entries');
    }
};
