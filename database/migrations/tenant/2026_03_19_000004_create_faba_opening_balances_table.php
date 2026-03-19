<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faba_opening_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->enum('material_type', ['fly_ash', 'bottom_ash']);
            $table->decimal('quantity', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->uuid('set_by')->nullable();
            $table->timestamp('set_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'material_type']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faba_opening_balances');
    }
};
