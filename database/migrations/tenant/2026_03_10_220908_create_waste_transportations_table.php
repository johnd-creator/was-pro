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
        Schema::create('waste_transportations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transportation_number')->unique();

            // Foreign keys
            $table->uuid('waste_record_id');
            $table->uuid('vendor_id');

            // Transportation details
            $table->date('transportation_date');
            $table->decimal('quantity', 12, 2); // Quantity being transported
            $table->string('unit', 20); // kg, ton, etc.
            $table->string('vehicle_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();

            // Status and workflow
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'cancelled'])->default('pending');

            // Notes and tracking
            $table->text('notes')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('waste_record_id')->references('id')->on('waste_records')->onDelete('restrict');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('restrict');

            // Indexes
            $table->index('transportation_number');
            $table->index('status');
            $table->index('transportation_date');
            $table->index('waste_record_id');
            $table->index('vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_transportations');
    }
};
