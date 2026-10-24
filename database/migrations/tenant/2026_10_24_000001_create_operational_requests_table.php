<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_requests', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('fingerprint', 64);
            $table->string('authorization', 64);
            $table->unsignedSmallInteger('status');
            $table->jsonb('response');
            $table->timestamp('created_at');
        });
        Schema::create('operational_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable();
            $table->string('module');
            $table->string('action');
            $table->uuid('reference_id');
            $table->jsonb('details');
            $table->timestamps();
            $table->index(['module', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_audit_logs');
        Schema::dropIfExists('operational_requests');
    }
};
