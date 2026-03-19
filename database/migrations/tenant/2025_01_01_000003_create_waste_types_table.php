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
        Schema::create('waste_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->uuid('category_id');
            $table->uuid('characteristic_id');
            $table->text('description')->nullable();
            $table->integer('storage_period_days')->default(0)->comment('Maximum storage period in days');
            $table->decimal('transport_cost', 12, 2)->default(0)->comment('Standard transport cost per unit');
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('waste_categories')->onDelete('restrict');
            $table->foreign('characteristic_id')->references('id')->on('waste_characteristics')->onDelete('restrict');

            $table->index('code');
            $table->index('category_id');
            $table->index('characteristic_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waste_types');
    }
};
