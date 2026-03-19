<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faba_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable();
            $table->string('action');
            $table->string('module');
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->text('summary');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['module', 'action']);
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faba_audit_logs');
    }
};
