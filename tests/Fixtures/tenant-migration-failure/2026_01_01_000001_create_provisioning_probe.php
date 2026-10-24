<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioning_probe', fn (Blueprint $table) => $table->integer('value'));
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_probe');
    }
};
