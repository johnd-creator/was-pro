<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faba_monthly_closing_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status')->default('approved');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('snapshot_payload');
            $table->json('warning_summary')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faba_monthly_closing_snapshots');
    }
};
