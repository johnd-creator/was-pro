<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faba_utilization_entries', function (Blueprint $table) {
            $table->uuid('internal_destination_id')->nullable()->after('vendor_id');
            $table->uuid('purpose_id')->nullable()->after('internal_destination_id');

            $table->foreign('internal_destination_id')->references('id')->on('faba_internal_destinations')->nullOnDelete();
            $table->foreign('purpose_id')->references('id')->on('faba_purposes')->nullOnDelete();
            $table->index('internal_destination_id');
            $table->index('purpose_id');
        });
    }

    public function down(): void
    {
        Schema::table('faba_utilization_entries', function (Blueprint $table) {
            $table->dropForeign(['internal_destination_id']);
            $table->dropForeign(['purpose_id']);
            $table->dropIndex(['internal_destination_id']);
            $table->dropIndex(['purpose_id']);
            $table->dropColumn(['internal_destination_id', 'purpose_id']);
        });
    }
};
