<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waste_haulings', function (Blueprint $table): void {
            $table->foreignUuid('vendor_id')->nullable()->constrained('vendors')->restrictOnDelete();
            $table->string('vehicle_number', 50)->nullable();
            $table->string('driver_name')->nullable();
            $table->string('document_number')->nullable();
            $table->string('destination')->nullable();
            $table->string('receiver')->nullable();
            $table->string('attachment_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('waste_haulings', function (Blueprint $table): void {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['vendor_id', 'vehicle_number', 'driver_name', 'document_number', 'destination', 'receiver', 'attachment_path']);
        });
    }
};
