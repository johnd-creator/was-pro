<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('waste_records', function (Blueprint $table): void {
            $table->foreignUuid('source_id')->nullable()->constrained('waste_sources')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('waste_sources')->exists()) {
            throw new RuntimeException('Sumber limbah telah digunakan; rollback harus mempertahankan riwayat.');
        }
        Schema::table('waste_records', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_id'));
        Schema::dropIfExists('waste_sources');
    }
};
