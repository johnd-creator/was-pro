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
        Schema::create('faba_production_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entry_number')->unique();
            $table->date('transaction_date');
            $table->enum('material_type', ['fly_ash', 'bottom_ash']);
            $table->enum('entry_type', ['production', 'pok', 'workshop', 'reject']);
            $table->decimal('quantity', 15, 2);
            $table->string('unit')->default('ton');
            $table->text('note')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('transaction_date');
            $table->index('material_type');
            $table->index('entry_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faba_production_entries');
    }
};
