<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        throw new RuntimeException('Forced provisioning migration failure.');
    }

    public function down(): void {}
};
