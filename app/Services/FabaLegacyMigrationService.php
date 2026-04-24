<?php

namespace App\Services;

use App\Models\FabaMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

class FabaLegacyMigrationService
{
    public const REFERENCE_TYPE_LEGACY_PRODUCTION = 'legacy_faba_production_entry';

    public const REFERENCE_TYPE_LEGACY_UTILIZATION = 'legacy_faba_utilization_entry';

    /**
     * @return array<string, mixed>
     */
    public function migrate(bool $dryRun = false): array
    {
        if (! Schema::hasTable('faba_movements')) {
            throw new RuntimeException('Tabel faba_movements belum tersedia pada schema tenant ini.');
        }

        return [
            'dry_run' => $dryRun,
            'production' => $this->migrateProductionEntries($dryRun),
            'utilization' => $this->migrateUtilizationEntries($dryRun),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateProductionEntries(bool $dryRun): array
    {
        if (! Schema::hasTable('faba_production_entries')) {
            return $this->emptySummary('Tabel legacy faba_production_entries tidak ditemukan.');
        }

        $rows = DB::table('faba_production_entries')
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get();

        $summary = $this->baseSummary($rows->count());

        foreach ($rows as $row) {
            $movementType = $this->mapLegacyProductionType((string) $row->entry_type);

            if ($movementType === null) {
                $summary['unmapped_count']++;
                $summary['unmapped_ids'][] = (string) $row->id;

                continue;
            }

            if ($this->movementAlreadyMigrated(self::REFERENCE_TYPE_LEGACY_PRODUCTION, (string) $row->id)) {
                $summary['already_migrated_count']++;

                continue;
            }

            $summary['migrated_count']++;
            $summary['migrated_ids'][] = (string) $row->id;

            if ($dryRun) {
                continue;
            }

            DB::table('faba_movements')->insert([
                'id' => (string) Str::uuid(),
                'transaction_date' => (string) $row->transaction_date,
                'material_type' => (string) $row->material_type,
                'movement_type' => $movementType,
                'stock_effect' => $this->stockEffectForMovement($movementType),
                'quantity' => round((float) $row->quantity, 2),
                'unit' => $row->unit ?: FabaMovement::DEFAULT_UNIT,
                'vendor_id' => null,
                'internal_destination_id' => null,
                'purpose_id' => null,
                'document_number' => null,
                'document_date' => null,
                'attachment_path' => null,
                'reference_type' => self::REFERENCE_TYPE_LEGACY_PRODUCTION,
                'reference_id' => (string) $row->id,
                'period_year' => $this->resolveLegacyPeriodYear($row),
                'period_month' => $this->resolveLegacyPeriodMonth($row),
                'approval_status' => FabaMovement::STATUS_APPROVED,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => $row->updated_by ?: $row->created_by,
                'approved_at' => $row->updated_at ?: $row->created_at ?: now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
                'created_by' => $row->created_by,
                'updated_by' => $row->updated_by,
                'note' => $this->buildLegacyNote(
                    sprintf('Migrated from legacy production entry %s.', (string) $row->entry_number),
                    $row->note,
                ),
                'created_at' => $row->created_at ?: now(),
                'updated_at' => $row->updated_at ?: $row->created_at ?: now(),
            ]);
        }

        $summary['skipped_count'] = $summary['already_migrated_count'] + $summary['unmapped_count'];

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateUtilizationEntries(bool $dryRun): array
    {
        if (! Schema::hasTable('faba_utilization_entries')) {
            return $this->emptySummary('Tabel legacy faba_utilization_entries tidak ditemukan.');
        }

        $rows = DB::table('faba_utilization_entries')
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get();

        $summary = $this->baseSummary($rows->count());

        foreach ($rows as $row) {
            $movementType = $this->mapLegacyUtilizationType((string) $row->utilization_type);

            if ($movementType === null) {
                $summary['unmapped_count']++;
                $summary['unmapped_ids'][] = (string) $row->id;

                continue;
            }

            if ($this->movementAlreadyMigrated(self::REFERENCE_TYPE_LEGACY_UTILIZATION, (string) $row->id)) {
                $summary['already_migrated_count']++;

                continue;
            }

            $summary['migrated_count']++;
            $summary['migrated_ids'][] = (string) $row->id;

            if ($dryRun) {
                continue;
            }

            DB::table('faba_movements')->insert([
                'id' => (string) Str::uuid(),
                'transaction_date' => (string) $row->transaction_date,
                'material_type' => (string) $row->material_type,
                'movement_type' => $movementType,
                'stock_effect' => $this->stockEffectForMovement($movementType),
                'quantity' => round((float) $row->quantity, 2),
                'unit' => $row->unit ?: FabaMovement::DEFAULT_UNIT,
                'vendor_id' => $row->vendor_id,
                'internal_destination_id' => Schema::hasColumn('faba_utilization_entries', 'internal_destination_id') ? $row->internal_destination_id : null,
                'purpose_id' => Schema::hasColumn('faba_utilization_entries', 'purpose_id') ? $row->purpose_id : null,
                'document_number' => $row->document_number,
                'document_date' => $row->document_date,
                'attachment_path' => $row->attachment_path,
                'reference_type' => self::REFERENCE_TYPE_LEGACY_UTILIZATION,
                'reference_id' => (string) $row->id,
                'period_year' => $this->resolveLegacyPeriodYear($row),
                'period_month' => $this->resolveLegacyPeriodMonth($row),
                'approval_status' => FabaMovement::STATUS_APPROVED,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => $row->updated_by ?: $row->created_by,
                'approved_at' => $row->updated_at ?: $row->created_at ?: now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
                'created_by' => $row->created_by,
                'updated_by' => $row->updated_by,
                'note' => $this->buildLegacyNote(
                    sprintf('Migrated from legacy utilization entry %s.', (string) $row->entry_number),
                    $row->note,
                ),
                'created_at' => $row->created_at ?: now(),
                'updated_at' => $row->updated_at ?: $row->created_at ?: now(),
            ]);
        }

        $summary['skipped_count'] = $summary['already_migrated_count'] + $summary['unmapped_count'];

        return $summary;
    }

    protected function movementAlreadyMigrated(string $referenceType, string $referenceId): bool
    {
        return FabaMovement::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists();
    }

    protected function mapLegacyProductionType(string $entryType): ?string
    {
        return match ($entryType) {
            'production' => FabaMovement::TYPE_PRODUCTION,
            'pok' => FabaMovement::TYPE_DISPOSAL_POK,
            'workshop' => FabaMovement::TYPE_WORKSHOP,
            'reject' => FabaMovement::TYPE_REJECT,
            default => null,
        };
    }

    protected function mapLegacyUtilizationType(string $utilizationType): ?string
    {
        return match ($utilizationType) {
            'external' => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            'internal' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
            default => null,
        };
    }

    protected function stockEffectForMovement(string $movementType): string
    {
        return in_array($movementType, [
            FabaMovement::TYPE_OPENING_BALANCE,
            FabaMovement::TYPE_PRODUCTION,
            FabaMovement::TYPE_WORKSHOP,
            FabaMovement::TYPE_ADJUSTMENT_IN,
        ], true) ? FabaMovement::STOCK_EFFECT_IN : FabaMovement::STOCK_EFFECT_OUT;
    }

    protected function resolveLegacyPeriodYear(stdClass $row): int
    {
        if (property_exists($row, 'period_year') && $row->period_year !== null) {
            return (int) $row->period_year;
        }

        return CarbonImmutable::parse((string) $row->transaction_date)->year;
    }

    protected function resolveLegacyPeriodMonth(stdClass $row): int
    {
        if (property_exists($row, 'period_month') && $row->period_month !== null) {
            return (int) $row->period_month;
        }

        return CarbonImmutable::parse((string) $row->transaction_date)->month;
    }

    protected function buildLegacyNote(string $prefix, ?string $note): string
    {
        return trim(implode("\n\n", array_filter([
            $prefix,
            $note,
        ])));
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseSummary(int $sourceCount): array
    {
        return [
            'source_count' => $sourceCount,
            'migrated_count' => 0,
            'already_migrated_count' => 0,
            'unmapped_count' => 0,
            'skipped_count' => 0,
            'migrated_ids' => [],
            'unmapped_ids' => [],
            'message' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptySummary(string $message): array
    {
        return [
            ...$this->baseSummary(0),
            'message' => $message,
        ];
    }
}
