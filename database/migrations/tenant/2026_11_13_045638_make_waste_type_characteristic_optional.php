<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waste_types', function (Blueprint $table) {
            $table->uuid('characteristic_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (\App\Models\WasteType::withTrashed()->whereNull('characteristic_id')->exists()) {
            throw new RuntimeException('Tidak dapat memulihkan karakteristik wajib ketika ada jenis limbah tanpa karakteristik. Data tidak diubah.');
        }

        Schema::table('waste_types', function (Blueprint $table) {
            $table->uuid('characteristic_id')->nullable(false)->change();
        });
    }
};
