<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('is_transporter')->nullable();
            $table->boolean('is_processor')->nullable();
            $table->boolean('is_utilizer')->nullable();
        });
        Schema::create('vendor_agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transporter_vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignUuid('receiver_vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('document_number', 150);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('waste_classification', 20);
            $table->string('document_status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['transporter_vendor_id', 'receiver_vendor_id', 'document_number', 'waste_classification'], 'vendor_agreement_parties_document_unique');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('vendor_agreements')->exists()
            || \Illuminate\Support\Facades\DB::table('vendors')->whereNotNull('is_transporter')->orWhereNotNull('is_processor')->orWhereNotNull('is_utilizer')->exists()) {
            throw new \RuntimeException('Rollback ditolak: MoU atau peran vendor sudah tercatat. Pertahankan histori dan gunakan migrasi korektif.');
        }
        Schema::dropIfExists('vendor_agreements');
        Schema::table('vendors', fn (Blueprint $table) => $table->dropColumn(['is_transporter', 'is_processor', 'is_utilizer']));
    }
};
