<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('faba_utilization_entries');
        Schema::dropIfExists('faba_production_entries');
    }

    public function down(): void
    {
        Schema::create('faba_production_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entry_number')->unique();
            $table->date('transaction_date');
            $table->enum('material_type', ['fly_ash', 'bottom_ash']);
            $table->enum('entry_type', ['production', 'pok', 'workshop', 'reject']);
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 10)->default('ton');
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['period_year', 'period_month']);
            $table->index('material_type');
            $table->index('entry_type');
        });

        Schema::create('faba_utilization_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entry_number')->unique();
            $table->date('transaction_date');
            $table->enum('material_type', ['fly_ash', 'bottom_ash']);
            $table->enum('utilization_type', ['external', 'internal']);
            $table->uuid('vendor_id')->nullable();
            $table->uuid('internal_destination_id')->nullable();
            $table->uuid('purpose_id')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 10)->default('ton');
            $table->string('document_number')->nullable();
            $table->date('document_date')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['period_year', 'period_month']);
            $table->index('material_type');
            $table->index('utilization_type');
        });
    }
};
