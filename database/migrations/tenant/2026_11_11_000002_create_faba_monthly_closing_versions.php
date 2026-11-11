<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection()->transaction(function (): void {
            DB::select("SELECT pg_advisory_xact_lock(hashtextextended(current_database() || ':' || current_schema() || ':operations', 0))");
            Schema::create('faba_monthly_closing_versions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->unsignedInteger('version');
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->json('snapshot_payload');
                $table->json('warning_summary')->nullable();
                $table->json('provenance');
                $table->timestamp('created_at');
                $table->unique(['year', 'month', 'version']);
            });

            // Copy JSON values directly, retaining legacy bytes and never recalculating history.
            if (Schema::hasTable('faba_monthly_closing_snapshots')) {
                DB::statement(<<<'SQL'
                    INSERT INTO faba_monthly_closing_versions
                        (id, year, month, version, approved_by, approved_at, snapshot_payload, warning_summary, provenance, created_at)
                    SELECT id, year, month, 1, approved_by, approved_at, snapshot_payload, warning_summary,
                        json_build_object('source', 'legacy_snapshot', 'schema', current_schema(),
                            'organization_name', null, 'approver_name', null, 'snapshot_id', id), CURRENT_TIMESTAMP
                    FROM faba_monthly_closing_snapshots WHERE status = 'approved'
                    SQL);
            }

            DB::unprepared(<<<'SQL'
                CREATE FUNCTION prevent_faba_closing_version_change() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    RAISE EXCEPTION 'Arsip closing bersifat append-only dan tidak dapat diubah atau dihapus.';
                END;
                $$;
                CREATE TRIGGER immutable_faba_closing_versions BEFORE UPDATE OR DELETE
                    ON faba_monthly_closing_versions FOR EACH ROW EXECUTE FUNCTION prevent_faba_closing_version_change();
                CREATE TRIGGER no_truncate_faba_closing_versions BEFORE TRUNCATE
                    ON faba_monthly_closing_versions FOR EACH STATEMENT EXECUTE FUNCTION prevent_faba_closing_version_change();
                SQL);
        });
    }

    public function down(): void
    {
        DB::connection()->transaction(function (): void {
            DB::select("SELECT pg_advisory_xact_lock(hashtextextended(current_database() || ':' || current_schema() || ':operations', 0))");
            if (DB::table('faba_monthly_closing_versions')->exists()) {
                throw new RuntimeException('Arsip closing berisi bukti persetujuan; rollback memerlukan rencana preservasi tersendiri.');
            }
            Schema::dropIfExists('faba_monthly_closing_versions');
            DB::statement('DROP FUNCTION IF EXISTS prevent_faba_closing_version_change()');
        });
    }
};
