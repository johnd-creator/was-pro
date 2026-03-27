<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('faba_movements')) {
            return;
        }

        DB::statement('ALTER TABLE faba_movements DROP CONSTRAINT IF EXISTS faba_movements_movement_type_check');
        DB::statement("
            ALTER TABLE faba_movements
            ADD CONSTRAINT faba_movements_movement_type_check
            CHECK (
                movement_type IN (
                    'opening_balance',
                    'production',
                    'workshop',
                    'utilization_external',
                    'utilization_internal',
                    'reject',
                    'disposal_pok',
                    'adjustment_in',
                    'adjustment_out'
                )
            )
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('faba_movements')) {
            return;
        }

        DB::statement('ALTER TABLE faba_movements DROP CONSTRAINT IF EXISTS faba_movements_movement_type_check');
        DB::statement("
            ALTER TABLE faba_movements
            ADD CONSTRAINT faba_movements_movement_type_check
            CHECK (
                movement_type IN (
                    'opening_balance',
                    'production',
                    'utilization_external',
                    'utilization_internal',
                    'reject',
                    'disposal_pok',
                    'adjustment_in',
                    'adjustment_out'
                )
            )
        ");
    }
};
