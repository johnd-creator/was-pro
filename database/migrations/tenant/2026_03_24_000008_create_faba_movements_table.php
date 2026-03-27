<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faba_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('transaction_date');
            $table->enum('material_type', ['fly_ash', 'bottom_ash']);
            $table->enum('movement_type', [
                'opening_balance',
                'production',
                'utilization_external',
                'utilization_internal',
                'reject',
                'disposal_pok',
                'adjustment_in',
                'adjustment_out',
            ]);
            $table->enum('stock_effect', ['in', 'out']);
            $table->decimal('quantity', 15, 2);
            $table->string('unit')->default('ton');
            $table->uuid('vendor_id')->nullable();
            $table->uuid('internal_destination_id')->nullable();
            $table->uuid('purpose_id')->nullable();
            $table->string('document_number')->nullable();
            $table->date('document_date')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['period_year', 'period_month']);
            $table->index(['material_type', 'movement_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['vendor_id', 'period_year', 'period_month']);
            $table->index(['internal_destination_id', 'period_year', 'period_month']);
            $table->index(['purpose_id', 'period_year', 'period_month']);

            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('internal_destination_id')->references('id')->on('faba_internal_destinations')->nullOnDelete();
            $table->foreign('purpose_id')->references('id')->on('faba_purposes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faba_movements');
    }
};
