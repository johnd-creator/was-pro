<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faba_tps_capacities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('material_type', ['fly_ash', 'bottom_ash'])->unique();
            $table->decimal('capacity', 15, 2);
            $table->decimal('warning_threshold', 5, 2)->default(80);
            $table->decimal('critical_threshold', 5, 2)->default(95);
            $table->uuid('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('material_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faba_tps_capacities');
    }
};
