<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('waste_records', function (Blueprint $table) {
            $table->date('expiry_date')->nullable()->after('date');
            $table->index('expiry_date');
        });

        // Update existing records with expiry dates
        DB::statement('
            UPDATE waste_records wr
            SET expiry_date = wr.date + (wt.storage_period_days || \' days\')::interval
            FROM waste_types wt
            WHERE wr.waste_type_id = wt.id
            AND wr.expiry_date IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waste_records', function (Blueprint $table) {
            $table->dropIndex(['expiry_date']);
            $table->dropColumn('expiry_date');
        });
    }
};
