<?php

namespace App\Services;

use App\Models\FabaMonthlyClosingSnapshot;
use App\Models\FabaMonthlyClosingVersion;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FabaClosingArchiveService
{
    public function available(): bool
    {
        // Resolve the physical tenant schema, not a same-named table elsewhere on search_path.
        return DB::selectOne("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'faba_monthly_closing_versions') AS available")->available;
    }

    public function versions(int $year, int $month): array
    {
        if (! $this->available()) {
            return [];
        }

        return FabaMonthlyClosingVersion::query()->where('year', $year)->where('month', $month)
            ->orderByDesc('version')->get(['id', 'version', 'approved_at', 'approved_by', 'provenance'])->toArray();
    }

    public function find(int $year, int $month, int $version): FabaMonthlyClosingVersion
    {
        abort_unless($this->available(), 503, 'Arsip closing berversi belum diaktifkan untuk organisasi ini.');

        return FabaMonthlyClosingVersion::query()->where('year', $year)->where('month', $month)
            ->where('version', $version)->firstOrFail();
    }

    public function append(FabaMonthlyClosingSnapshot $snapshot): void
    {
        if (! $this->available()) {
            return;
        }

        $schema = DB::selectOne('SELECT current_schema() AS name')->name;
        $organization = Organization::query()->where('schema_name', $schema)->first();
        $version = (int) FabaMonthlyClosingVersion::query()->where('year', $snapshot->year)
            ->where('month', $snapshot->month)->max('version') + 1;

        FabaMonthlyClosingVersion::query()->create([
            'year' => $snapshot->year, 'month' => $snapshot->month, 'version' => $version,
            'approved_by' => $snapshot->approved_by, 'approved_at' => $snapshot->approved_at,
            'snapshot_payload' => $snapshot->snapshot_payload, 'warning_summary' => $snapshot->warning_summary,
            'provenance' => [
                'source' => 'approval', 'schema' => $schema, 'snapshot_id' => $snapshot->id,
                'organization_id' => $organization?->id, 'organization_name' => $organization?->name,
                'organization_code' => $organization?->code,
                'approver_name' => User::query()->find($snapshot->approved_by)?->name,
            ],
            'created_at' => now(),
        ]);
    }
}
