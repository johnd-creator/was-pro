<?php

namespace App\Services;

use App\Models\FabaAuditLog;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaOpeningBalance;
use App\Models\FabaProductionEntry;
use App\Models\FabaUtilizationEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FabaRecapService
{
    /**
     * @return array{year: int, month: int}|null
     */
    public function getLatestAvailablePeriod(): ?array
    {
        $productionPeriod = FabaProductionEntry::query()
            ->selectRaw('extract(year from transaction_date)::integer as year, extract(month from transaction_date)::integer as month')
            ->orderByRaw('extract(year from transaction_date) desc, extract(month from transaction_date) desc')
            ->first();

        $utilizationPeriod = FabaUtilizationEntry::query()
            ->selectRaw('extract(year from transaction_date)::integer as year, extract(month from transaction_date)::integer as month')
            ->orderByRaw('extract(year from transaction_date) desc, extract(month from transaction_date) desc')
            ->first();

        $periods = collect([$productionPeriod, $utilizationPeriod])
            ->filter()
            ->map(fn ($period): array => [
                'year' => (int) $period->year,
                'month' => (int) $period->month,
            ])
            ->sortByDesc(fn (array $period): string => sprintf('%04d-%02d', $period['year'], $period['month']))
            ->values();

        return $periods->first();
    }

    /**
     * @return array{year: int, month: int, resolved_from_latest: bool}
     */
    public function resolveRequestedOrLatestPeriod(?int $year = null, ?int $month = null): array
    {
        if ($year !== null && $month !== null) {
            return [
                'year' => $year,
                'month' => $month,
                'resolved_from_latest' => false,
            ];
        }

        $latestPeriod = $this->getLatestAvailablePeriod();

        if ($latestPeriod) {
            return [
                'year' => $latestPeriod['year'],
                'month' => $latestPeriod['month'],
                'resolved_from_latest' => true,
            ];
        }

        return [
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'resolved_from_latest' => false,
        ];
    }

    /**
     * @return array<int, array{year: int, month: int, period_label: string}>
     */
    public function getAvailablePeriodOptions(): array
    {
        $productionPeriods = FabaProductionEntry::query()
            ->selectRaw('extract(year from transaction_date)::integer as year, extract(month from transaction_date)::integer as month')
            ->groupByRaw('extract(year from transaction_date), extract(month from transaction_date)')
            ->get();

        $utilizationPeriods = FabaUtilizationEntry::query()
            ->selectRaw('extract(year from transaction_date)::integer as year, extract(month from transaction_date)::integer as month')
            ->groupByRaw('extract(year from transaction_date), extract(month from transaction_date)')
            ->get();

        return $productionPeriods
            ->concat($utilizationPeriods)
            ->map(fn ($item): string => sprintf('%04d-%02d', (int) $item->year, (int) $item->month))
            ->unique()
            ->map(function (string $period): array {
                [$year, $month] = array_map('intval', explode('-', $period));

                return [
                    'year' => $year,
                    'month' => $month,
                    'period_label' => $this->formatPeriodLabel($year, $month),
                ];
            })
            ->sortByDesc(fn (array $period): string => sprintf('%04d-%02d', $period['year'], $period['month']))
            ->values()
            ->all();
    }

    public function getMonthlyRecap(int $year, int $month): array
    {
        $production = FabaProductionEntry::query()->forPeriod($year, $month);
        $utilization = FabaUtilizationEntry::query()->forPeriod($year, $month);

        $productionFlyAsh = (float) (clone $production)
            ->where('material_type', FabaProductionEntry::MATERIAL_FLY_ASH)
            ->sum('quantity');
        $productionBottomAsh = (float) (clone $production)
            ->where('material_type', FabaProductionEntry::MATERIAL_BOTTOM_ASH)
            ->sum('quantity');
        $utilizationFlyAsh = (float) (clone $utilization)
            ->where('material_type', FabaUtilizationEntry::MATERIAL_FLY_ASH)
            ->sum('quantity');
        $utilizationBottomAsh = (float) (clone $utilization)
            ->where('material_type', FabaUtilizationEntry::MATERIAL_BOTTOM_ASH)
            ->sum('quantity');

        $openingFlyAsh = $this->getOpeningBalanceForMaterial($year, $month, FabaProductionEntry::MATERIAL_FLY_ASH);
        $openingBottomAsh = $this->getOpeningBalanceForMaterial($year, $month, FabaProductionEntry::MATERIAL_BOTTOM_ASH);
        $closingFlyAsh = round($openingFlyAsh + $productionFlyAsh - $utilizationFlyAsh, 2);
        $closingBottomAsh = round($openingBottomAsh + $productionBottomAsh - $utilizationBottomAsh, 2);
        $totalProduction = round($productionFlyAsh + $productionBottomAsh, 2);
        $totalUtilization = round($utilizationFlyAsh + $utilizationBottomAsh, 2);
        $openingBalance = round($openingFlyAsh + $openingBottomAsh, 2);
        $closingBalance = round($closingFlyAsh + $closingBottomAsh, 2);

        $approval = $this->getMonthlyApproval($year, $month)?->load([
            'submittedByUser:id,name',
            'approvedByUser:id,name',
            'rejectedByUser:id,name',
        ]);

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => $this->formatPeriodLabel($year, $month),
            'production_fly_ash' => round($productionFlyAsh, 2),
            'production_bottom_ash' => round($productionBottomAsh, 2),
            'utilization_fly_ash' => round($utilizationFlyAsh, 2),
            'utilization_bottom_ash' => round($utilizationBottomAsh, 2),
            'opening_fly_ash' => $openingFlyAsh,
            'opening_bottom_ash' => $openingBottomAsh,
            'closing_fly_ash' => $closingFlyAsh,
            'closing_bottom_ash' => $closingBottomAsh,
            'total_production' => $totalProduction,
            'total_utilization' => $totalUtilization,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'warning_negative_balance' => $closingBalance < 0,
            'warning_utilization_without_production' => $totalProduction <= 0 && $totalUtilization > 0,
            'warning_missing_opening_balance' => ! $this->hasOpeningBalanceSource($year, $month),
            'production_entries_count' => (clone $production)->count(),
            'utilization_entries_count' => (clone $utilization)->count(),
            'approval' => $approval,
            'warnings' => $this->getWarnings($year, $month, [
                'total_production' => $totalProduction,
                'total_utilization' => $totalUtilization,
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
            ]),
        ];
    }

    public function getMonthlyRecapDetail(int $year, int $month): array
    {
        $recap = $this->getMonthlyRecap($year, $month);

        $productionEntries = FabaProductionEntry::query()
            ->forPeriod($year, $month)
            ->with(['createdByUser:id,name', 'updatedByUser:id,name'])
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get()
            ->map(fn (FabaProductionEntry $entry): array => [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'transaction_date' => $entry->transaction_date->format('Y-m-d'),
                'material_type' => $entry->material_type,
                'entry_type' => $entry->entry_type,
                'quantity' => (float) $entry->quantity,
                'unit' => $entry->unit,
                'note' => $entry->note,
                'created_by_user' => $entry->createdByUser,
            ])
            ->values();

        $utilizationEntries = FabaUtilizationEntry::query()
            ->forPeriod($year, $month)
            ->with(['vendor:id,name', 'createdByUser:id,name', 'updatedByUser:id,name'])
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get()
            ->map(fn (FabaUtilizationEntry $entry): array => [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'transaction_date' => $entry->transaction_date->format('Y-m-d'),
                'material_type' => $entry->material_type,
                'utilization_type' => $entry->utilization_type,
                'vendor_id' => $entry->vendor_id,
                'vendor' => $entry->vendor,
                'quantity' => (float) $entry->quantity,
                'unit' => $entry->unit,
                'document_number' => $entry->document_number,
                'document_date' => $entry->document_date?->format('Y-m-d'),
                'attachment_path' => $entry->attachment_path,
                'note' => $entry->note,
                'created_by_user' => $entry->createdByUser,
            ])
            ->values();

        $auditLogs = FabaAuditLog::query()
            ->with('actor:id,name')
            ->forPeriod($year, $month)
            ->latest()
            ->get()
            ->map(fn (FabaAuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'module' => $log->module,
                'summary' => $log->summary,
                'details' => $log->details,
                'actor' => $log->actor,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            ])
            ->values();

        return [
            'recap' => $recap,
            'production_entries' => $productionEntries,
            'utilization_entries' => $utilizationEntries,
            'opening_balances' => [
                [
                    'material_type' => FabaProductionEntry::MATERIAL_FLY_ASH,
                    'quantity' => $recap['opening_fly_ash'],
                ],
                [
                    'material_type' => FabaProductionEntry::MATERIAL_BOTTOM_ASH,
                    'quantity' => $recap['opening_bottom_ash'],
                ],
            ],
            'audit_logs' => $auditLogs,
        ];
    }

    public function getYearlyRecap(int $year): array
    {
        $months = collect(range(1, 12))
            ->map(fn (int $month): array => $this->getMonthlyRecap($year, $month))
            ->values();

        return [
            'year' => $year,
            'months' => $months,
            'totals' => [
                'total_production' => round((float) $months->sum('total_production'), 2),
                'total_utilization' => round((float) $months->sum('total_utilization'), 2),
                'closing_balance' => round((float) ($months->last()['closing_balance'] ?? 0), 2),
            ],
            'trend' => $months->map(fn (array $month): array => [
                'month' => $month['month'],
                'label' => $this->formatMonthLabel($month['month']),
                'production' => $month['total_production'],
                'utilization' => $month['total_utilization'],
                'closing_balance' => $month['closing_balance'],
            ])->values(),
        ];
    }

    public function getVendorRecap(int $year, ?string $vendorId = null): array
    {
        $baseQuery = FabaUtilizationEntry::query()
            ->with('vendor:id,name')
            ->where('utilization_type', FabaUtilizationEntry::TYPE_EXTERNAL)
            ->whereYear('transaction_date', $year);

        if ($vendorId) {
            $baseQuery->where('vendor_id', $vendorId);
        }

        $entries = $baseQuery->get();
        $vendors = $entries
            ->groupBy('vendor_id')
            ->map(function (Collection $group): array {
                /** @var FabaUtilizationEntry|null $first */
                $first = $group->first();

                return [
                    'vendor_id' => $first?->vendor_id,
                    'vendor_name' => $first?->vendor?->name ?? 'Tanpa Vendor',
                    'total_quantity' => round((float) $group->sum('quantity'), 2),
                    'transactions_count' => $group->count(),
                    'materials' => $group->pluck('material_type')->unique()->values()->all(),
                    'history' => $group->map(fn (FabaUtilizationEntry $entry): array => [
                        'id' => $entry->id,
                        'transaction_date' => $entry->transaction_date->format('Y-m-d'),
                        'entry_number' => $entry->entry_number,
                        'material_type' => $entry->material_type,
                        'quantity' => (float) $entry->quantity,
                        'unit' => $entry->unit,
                    ])->values(),
                    'monthly_history' => collect(range(1, 12))->map(fn (int $month): array => [
                        'month' => $month,
                        'label' => $this->formatMonthLabel($month),
                        'quantity' => round((float) $group->filter(
                            fn (FabaUtilizationEntry $entry): bool => (int) $entry->transaction_date->format('n') === $month
                        )->sum('quantity'), 2),
                    ])->values(),
                ];
            })
            ->values();

        return [
            'year' => $year,
            'vendors' => $vendors,
        ];
    }

    public function getCurrentBalance(): float
    {
        $period = $this->resolveRequestedOrLatestPeriod();
        $recap = $this->getMonthlyRecap($period['year'], $period['month']);

        return $recap['closing_balance'];
    }

    public function isPeriodLocked(int $year, int $month): bool
    {
        return FabaMonthlyApproval::query()
            ->forPeriod($year, $month)
            ->whereIn('status', [
                FabaMonthlyApproval::STATUS_SUBMITTED,
                FabaMonthlyApproval::STATUS_APPROVED,
            ])
            ->exists();
    }

    public function getOrCreateMonthlyApproval(int $year, int $month): FabaMonthlyApproval
    {
        return FabaMonthlyApproval::query()->firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['status' => FabaMonthlyApproval::STATUS_DRAFT]
        );
    }

    public function getMonthlyApproval(int $year, int $month): ?FabaMonthlyApproval
    {
        return FabaMonthlyApproval::query()
            ->forPeriod($year, $month)
            ->first();
    }

    public function getPeriodStatus(int $year, int $month): string
    {
        return $this->getMonthlyApproval($year, $month)?->status ?? FabaMonthlyApproval::STATUS_DRAFT;
    }

    public function getAvailablePeriods(int $year): Collection
    {
        $productionPeriods = FabaProductionEntry::query()
            ->selectRaw('extract(year from transaction_date)::integer as year, extract(month from transaction_date)::integer as month')
            ->groupByRaw('extract(year from transaction_date), extract(month from transaction_date)')
            ->whereYear('transaction_date', $year)
            ->get();

        $utilizationPeriods = FabaUtilizationEntry::query()
            ->selectRaw('extract(year from transaction_date)::integer as year, extract(month from transaction_date)::integer as month')
            ->groupByRaw('extract(year from transaction_date), extract(month from transaction_date)')
            ->whereYear('transaction_date', $year)
            ->get();

        return $productionPeriods
            ->concat($utilizationPeriods)
            ->map(fn ($item): string => $item->year.'-'.$item->month)
            ->unique()
            ->map(function (string $period) use ($year): array {
                [, $month] = explode('-', $period);
                $month = (int) $month;
                $recap = $this->getMonthlyRecap($year, $month);
                $approval = $recap['approval'];

                return [
                    'year' => $year,
                    'month' => $month,
                    'period_label' => $this->formatPeriodLabel($year, $month),
                    'status' => $approval?->status ?? FabaMonthlyApproval::STATUS_DRAFT,
                    'can_submit' => $approval?->canSubmit() ?? true,
                    'can_approve' => $approval?->canApprove() ?? false,
                    'can_reject' => $approval?->canReject() ?? false,
                    'can_review' => true,
                    'can_reopen' => $approval?->canReopen() ?? false,
                    'recap' => $recap,
                ];
            })
            ->sortBy('month')
            ->values();
    }

    public function getPeriodMeta(int $year, int $month): array
    {
        $approval = $this->getMonthlyApproval($year, $month) ?? FabaMonthlyApproval::draftForPeriod($year, $month);

        return [
            'id' => $approval->id,
            'year' => $year,
            'month' => $month,
            'period_label' => $this->formatPeriodLabel($year, $month),
            'status' => $approval->status,
            'rejection_note' => $approval->rejection_note,
            'submitted_at' => $approval->submitted_at?->format('Y-m-d H:i:s'),
            'approved_at' => $approval->approved_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $approval->rejected_at?->format('Y-m-d H:i:s'),
            'submitted_by_user' => $approval->submittedByUser,
            'approved_by_user' => $approval->approvedByUser,
            'rejected_by_user' => $approval->rejectedByUser,
            'can_submit' => $approval->canSubmit(),
            'can_approve' => $approval->canApprove(),
            'can_reject' => $approval->canReject(),
            'can_review' => true,
            'can_reopen' => $approval->canReopen(),
        ];
    }

    public function setOpeningBalance(int $year, int $month, string $materialType, float $quantity, ?string $note, ?string $userId): FabaOpeningBalance
    {
        $balance = FabaOpeningBalance::query()->updateOrCreate(
            [
                'year' => $year,
                'month' => $month,
                'material_type' => $materialType,
            ],
            [
                'quantity' => round($quantity, 2),
                'note' => $note,
                'set_by' => $userId,
                'set_at' => now(),
            ]
        );

        return $balance;
    }

    public function getOpeningBalance(int $year, int $month): float
    {
        return round(
            $this->getOpeningBalanceForMaterial($year, $month, FabaProductionEntry::MATERIAL_FLY_ASH)
                + $this->getOpeningBalanceForMaterial($year, $month, FabaProductionEntry::MATERIAL_BOTTOM_ASH),
            2
        );
    }

    public function formatPeriodLabel(int $year, int $month): string
    {
        return $this->formatMonthLabel($month).' '.$year;
    }

    public function formatMonthLabel(int $month): string
    {
        $labels = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $labels[$month] ?? (string) $month;
    }

    protected function getOpeningBalanceForMaterial(int $year, int $month, string $materialType): float
    {
        $explicit = FabaOpeningBalance::query()
            ->forPeriod($year, $month)
            ->where('material_type', $materialType)
            ->first();

        if ($explicit) {
            return round((float) $explicit->quantity, 2);
        }

        $previousPeriod = CarbonImmutable::create($year, $month, 1)->subMonth();

        if (! $this->hasPriorActivity($year, $month, $materialType)) {
            return 0.0;
        }

        return round(
            $this->getOpeningBalanceForMaterial(
                (int) $previousPeriod->format('Y'),
                (int) $previousPeriod->format('n'),
                $materialType,
            )
            + (float) FabaProductionEntry::query()
                ->forPeriod((int) $previousPeriod->format('Y'), (int) $previousPeriod->format('n'))
                ->where('material_type', $materialType)
                ->sum('quantity')
            - (float) FabaUtilizationEntry::query()
                ->forPeriod((int) $previousPeriod->format('Y'), (int) $previousPeriod->format('n'))
                ->where('material_type', $materialType)
                ->sum('quantity'),
            2
        );
    }

    protected function hasPriorActivity(int $year, int $month, string $materialType): bool
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay()->toDateString();

        return FabaProductionEntry::query()
            ->where('material_type', $materialType)
            ->where('transaction_date', '<', $periodStart)
            ->exists()
            || FabaUtilizationEntry::query()
                ->where('material_type', $materialType)
                ->where('transaction_date', '<', $periodStart)
                ->exists()
            || FabaOpeningBalance::query()
                ->where('material_type', $materialType)
                ->where(function ($query) use ($year, $month) {
                    $query->where('year', '<', $year)
                        ->orWhere(function ($nested) use ($year, $month) {
                            $nested->where('year', $year)->where('month', '<', $month);
                        });
                })
                ->exists();
    }

    protected function hasOpeningBalanceSource(int $year, int $month): bool
    {
        if (FabaOpeningBalance::query()->forPeriod($year, $month)->exists()) {
            return true;
        }

        return ! $this->hasPriorActivity($year, $month, FabaProductionEntry::MATERIAL_FLY_ASH)
            && ! $this->hasPriorActivity($year, $month, FabaProductionEntry::MATERIAL_BOTTOM_ASH);
    }

    protected function getWarnings(int $year, int $month, array $context): array
    {
        $warnings = [];

        if ($context['closing_balance'] < 0) {
            $warnings[] = [
                'code' => 'negative_balance',
                'message' => 'Saldo akhir periode ini negatif.',
            ];
        }

        if ($context['total_utilization'] > ($context['opening_balance'] + $context['total_production'])) {
            $warnings[] = [
                'code' => 'utilization_exceeds_stock',
                'message' => 'Pemanfaatan melebihi stok yang tersedia.',
            ];
        }

        if ($context['total_utilization'] > 0 && $context['total_production'] <= 0 && $context['opening_balance'] <= 0) {
            $warnings[] = [
                'code' => 'utilization_without_stock_source',
                'message' => 'Ada pemanfaatan tanpa produksi maupun saldo awal yang cukup.',
            ];
        }

        if (! $this->hasOpeningBalanceSource($year, $month)) {
            $warnings[] = [
                'code' => 'missing_opening_balance',
                'message' => 'Opening balance historis belum ditentukan untuk periode ini.',
            ];
        }

        $invalidExternalDocuments = FabaUtilizationEntry::query()
            ->forPeriod($year, $month)
            ->where('utilization_type', FabaUtilizationEntry::TYPE_EXTERNAL)
            ->where(function ($query) {
                $query->whereNull('vendor_id')
                    ->orWhereNull('document_number')
                    ->orWhereNull('document_date');
            })
            ->count();

        if ($invalidExternalDocuments > 0) {
            $warnings[] = [
                'code' => 'external_document_incomplete',
                'message' => 'Masih ada transaksi eksternal yang dokumennya belum lengkap.',
            ];
        }

        if ($this->getPeriodStatus($year, $month) === FabaMonthlyApproval::STATUS_SUBMITTED && $invalidExternalDocuments > 0) {
            $warnings[] = [
                'code' => 'submitted_period_incomplete',
                'message' => 'Periode sudah diajukan tetapi masih ada data yang belum lengkap.',
            ];
        }

        return $warnings;
    }
}
