<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_notifications', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->uuid('event_id');
            $table->uuid('organization_id');
            $table->uuid('recipient_id');
            $table->string('resource_type', 40);
            $table->uuid('resource_id');
            $table->string('event', 20);
            $table->timestamp('occurred_at');
            $table->timestamp('read_at')->nullable();
            $table->unique(['event_id', 'recipient_id']);
            $table->index(['organization_id', 'recipient_id', 'read_at']);
            $table->index(['recipient_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_notifications');
    }
};
