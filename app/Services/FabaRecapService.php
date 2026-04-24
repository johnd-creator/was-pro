<?php

namespace App\Services;

use App\Models\FabaAuditLog;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMonthlyClosingSnapshot;
use App\Models\FabaMovement;
use App\Models\FabaOpeningBalance;
use App\Models\FabaTpsCapacity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FabaRecapService
{
    /**
     * @return array{year: int, month: int}|null
     */
    public function getLatestAvailablePeriod(): ?array
    {
        $movementPeriod = FabaMovement::query()
            ->approved()
            ->select(['period_year as year', 'period_month as month'])
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        $periods = collect([$movementPeriod])
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
        return FabaMovement::query()
            ->approved()
            ->select(['period_year as year', 'period_month as month'])
            ->groupBy('period_year', 'period_month')
            ->get()
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
        $movements = FabaMovement::query()->approved()->forPeriod($year, $month);

        $productionFlyAsh = $this->sumMovementTypes((clone $movements), FabaMovement::MATERIAL_FLY_ASH, [
            FabaMovement::TYPE_PRODUCTION,
            FabaMovement::TYPE_WORKSHOP,
        ]);
        $productionBottomAsh = $this->sumMovementTypes((clone $movements), FabaMovement::MATERIAL_BOTTOM_ASH, [
            FabaMovement::TYPE_PRODUCTION,
            FabaMovement::TYPE_WORKSHOP,
        ]);
        $utilizationFlyAsh = $this->sumMovementTypes((clone $movements), FabaMovement::MATERIAL_FLY_ASH, [
            FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            FabaMovement::TYPE_UTILIZATION_INTERNAL,
        ]);
        $utilizationBottomAsh = $this->sumMovementTypes((clone $movements), FabaMovement::MATERIAL_BOTTOM_ASH, [
            FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            FabaMovement::TYPE_UTILIZATION_INTERNAL,
        ]);

        $openingFlyAsh = $this->getOpeningBalanceForMaterial($year, $month, FabaMovement::MATERIAL_FLY_ASH);
        $openingBottomAsh = $this->getOpeningBalanceForMaterial($year, $month, FabaMovement::MATERIAL_BOTTOM_ASH);
        $closingFlyAsh = round($openingFlyAsh + $this->sumInflows((clone $movements), FabaMovement::MATERIAL_FLY_ASH) - $this->sumOutflows((clone $movements), FabaMovement::MATERIAL_FLY_ASH), 2);
        $closingBottomAsh = round($openingBottomAsh + $this->sumInflows((clone $movements), FabaMovement::MATERIAL_BOTTOM_ASH) - $this->sumOutflows((clone $movements), FabaMovement::MATERIAL_BOTTOM_ASH), 2);
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
            'production_movements_count' => FabaMovement::query()
                ->approved()
                ->forPeriod($year, $month)
                ->whereIn('movement_type', [FabaMovement::TYPE_PRODUCTION, FabaMovement::TYPE_WORKSHOP, FabaMovement::TYPE_REJECT, FabaMovement::TYPE_DISPOSAL_POK])
                ->count(),
            'utilization_movements_count' => FabaMovement::query()
                ->approved()
                ->forPeriod($year, $month)
                ->whereIn('movement_type', [FabaMovement::TYPE_UTILIZATION_EXTERNAL, FabaMovement::TYPE_UTILIZATION_INTERNAL])
                ->count(),
            'approval' => $approval,
            'movement_summary' => [
                'inflow_fly_ash' => $this->sumInflows((clone $movements), FabaMovement::MATERIAL_FLY_ASH),
                'inflow_bottom_ash' => $this->sumInflows((clone $movements), FabaMovement::MATERIAL_BOTTOM_ASH),
                'outflow_fly_ash' => $this->sumOutflows((clone $movements), FabaMovement::MATERIAL_FLY_ASH),
                'outflow_bottom_ash' => $this->sumOutflows((clone $movements), FabaMovement::MATERIAL_BOTTOM_ASH),
            ],
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
        $snapshot = $this->getMonthlyClosingSnapshot($year, $month);

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

        $movements = FabaMovement::query()
            ->approved()
            ->forPeriod($year, $month)
            ->with(['vendor:id,name', 'internalDestination:id,name', 'purpose:id,name'])
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get()
            ->map(fn (FabaMovement $movement): array => [
                'id' => $movement->id,
                'transaction_date' => $movement->transaction_date->format('Y-m-d'),
                'material_type' => $movement->material_type,
                'movement_type' => $movement->movement_type,
                'stock_effect' => $movement->stock_effect,
                'quantity' => (float) $movement->quantity,
                'unit' => $movement->unit,
                'vendor' => $movement->vendor,
                'internal_destination' => $movement->internalDestination,
                'purpose' => $movement->purpose,
                'document_number' => $movement->document_number,
                'document_date' => $movement->document_date?->format('Y-m-d'),
                'reference_type' => $movement->reference_type,
                'reference_id' => $movement->reference_id,
                'note' => $movement->note,
                'display_number' => $this->formatMovementDisplayNumber($movement),
            ])
            ->values();

        $vendorBreakdown = $this->getVendorBreakdown($year, $month);
        $internalDestinationBreakdown = $this->getInternalDestinationBreakdown($year, $month);
        $purposeBreakdown = $this->getPurposeBreakdown($year, $month);

        return [
            'recap' => $recap,
            'snapshot' => $snapshot ? [
                'id' => $snapshot->id,
                'year' => $snapshot->year,
                'month' => $snapshot->month,
                'status' => $snapshot->status,
                'approved_at' => $snapshot->approved_at?->format('Y-m-d H:i:s'),
                'approved_by_user' => $snapshot->approvedByUser,
                'warning_summary' => $snapshot->warning_summary,
                'snapshot_payload' => $snapshot->snapshot_payload,
            ] : null,
            'movements' => $movements,
            'opening_balances' => [
                [
                    'material_type' => FabaMovement::MATERIAL_FLY_ASH,
                    'quantity' => $recap['opening_fly_ash'],
                ],
                [
                    'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
                    'quantity' => $recap['opening_bottom_ash'],
                ],
            ],
            'vendor_breakdown' => $vendorBreakdown,
            'internal_destination_breakdown' => $internalDestinationBreakdown,
            'purpose_breakdown' => $purposeBreakdown,
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
                'warning_count' => count($month['warnings']),
                'has_warning' => count($month['warnings']) > 0,
            ])->values(),
        ];
    }

    public function getVendorRecap(int $year, ?string $vendorId = null): array
    {
        $baseQuery = FabaMovement::query()
            ->approved()
            ->with('vendor:id,name')
            ->where('movement_type', FabaMovement::TYPE_UTILIZATION_EXTERNAL)
            ->where('period_year', $year);

        if ($vendorId) {
            $baseQuery->where('vendor_id', $vendorId);
        }

        $entries = $baseQuery->get();
        $vendors = $entries
            ->groupBy('vendor_id')
            ->map(function (Collection $group): array {
                /** @var FabaMovement|null $first */
                $first = $group->first();

                return [
                    'vendor_id' => $first?->vendor_id,
                    'vendor_name' => $first?->vendor?->name ?? 'Tanpa Vendor',
                    'total_quantity' => round((float) $group->sum('quantity'), 2),
                    'transactions_count' => $group->count(),
                    'materials' => $group->pluck('material_type')->unique()->values()->all(),
                    'history' => $group->map(fn (FabaMovement $entry): array => [
                        'id' => $entry->id,
                        'transaction_date' => $entry->transaction_date->format('Y-m-d'),
                        'display_number' => $this->formatMovementDisplayNumber($entry),
                        'material_type' => $entry->material_type,
                        'quantity' => (float) $entry->quantity,
                        'unit' => $entry->unit,
                    ])->values(),
                    'monthly_history' => collect(range(1, 12))->map(fn (int $month): array => [
                        'month' => $month,
                        'label' => $this->formatMonthLabel($month),
                        'quantity' => round((float) $group->filter(
                            fn (FabaMovement $entry): bool => (int) $entry->transaction_date->format('n') === $month
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

    public function getInternalDestinationRecap(int $year, ?string $internalDestinationId = null): array
    {
        $baseQuery = FabaMovement::query()
            ->approved()
            ->with('internalDestination:id,name')
            ->where('movement_type', FabaMovement::TYPE_UTILIZATION_INTERNAL)
            ->where('period_year', $year);

        if ($internalDestinationId) {
            $baseQuery->where('internal_destination_id', $internalDestinationId);
        }

        $entries = $baseQuery->get();
        $destinations = $entries
            ->groupBy('internal_destination_id')
            ->map(function (Collection $group): array {
                /** @var FabaMovement|null $first */
                $first = $group->first();

                return [
                    'internal_destination_id' => $first?->internal_destination_id,
                    'internal_destination_name' => $first?->internalDestination?->name ?? 'Tanpa Tujuan',
                    'total_quantity' => round((float) $group->sum('quantity'), 2),
                    'transactions_count' => $group->count(),
                    'materials' => $group->pluck('material_type')->unique()->values()->all(),
                    'history' => $group->map(fn (FabaMovement $entry): array => [
                        'id' => $entry->id,
                        'transaction_date' => $entry->transaction_date->format('Y-m-d'),
                        'display_number' => $this->formatMovementDisplayNumber($entry),
                        'material_type' => $entry->material_type,
                        'quantity' => (float) $entry->quantity,
                        'unit' => $entry->unit,
                    ])->values(),
                ];
            })
            ->values();

        return [
            'year' => $year,
            'destinations' => $destinations,
        ];
    }

    public function getPurposeRecap(int $year, ?string $purposeId = null): array
    {
        $baseQuery = FabaMovement::query()
            ->approved()
            ->with('purpose:id,name')
            ->whereNotNull('purpose_id')
            ->where('period_year', $year);

        if ($purposeId) {
            $baseQuery->where('purpose_id', $purposeId);
        }

        $entries = $baseQuery->get();
        $purposes = $entries
            ->groupBy('purpose_id')
            ->map(function (Collection $group): array {
                /** @var FabaMovement|null $first */
                $first = $group->first();

                return [
                    'purpose_id' => $first?->purpose_id,
                    'purpose_name' => $first?->purpose?->name ?? 'Tanpa Use-case',
                    'total_quantity' => round((float) $group->sum('quantity'), 2),
                    'transactions_count' => $group->count(),
                    'materials' => $group->pluck('material_type')->unique()->values()->all(),
                    'history' => $group->map(fn (FabaMovement $entry): array => [
                        'id' => $entry->id,
                        'transaction_date' => $entry->transaction_date->format('Y-m-d'),
                        'display_number' => $this->formatMovementDisplayNumber($entry),
                        'movement_type' => $entry->movement_type,
                        'material_type' => $entry->material_type,
                        'quantity' => (float) $entry->quantity,
                        'unit' => $entry->unit,
                    ])->values(),
                ];
            })
            ->values();

        return [
            'year' => $year,
            'purposes' => $purposes,
        ];
    }

    public function getStockCard(int $year, ?int $month = null, ?string $materialType = null): array
    {
        $baseQuery = FabaMovement::query()
            ->approved()
            ->with(['vendor:id,name', 'internalDestination:id,name', 'purpose:id,name'])
            ->where('period_year', $year)
            ->when($month, fn ($query) => $query->where('period_month', $month))
            ->when($materialType, fn ($query) => $query->where('material_type', $materialType))
            ->orderBy('transaction_date')
            ->orderBy('created_at');

        $movements = $baseQuery->get();
        $runningBalances = [];

        $rows = $movements->map(function (FabaMovement $movement) use (&$runningBalances): array {
            $materialType = $movement->material_type;
            $runningBalances[$materialType] ??= 0.0;
            $delta = $movement->stock_effect === FabaMovement::STOCK_EFFECT_IN
                ? (float) $movement->quantity
                : -1 * (float) $movement->quantity;
            $runningBalances[$materialType] = round($runningBalances[$materialType] + $delta, 2);

            return [
                'id' => $movement->id,
                'transaction_date' => $movement->transaction_date->format('Y-m-d'),
                'display_number' => $this->formatMovementDisplayNumber($movement),
                'material_type' => $movement->material_type,
                'movement_type' => $movement->movement_type,
                'stock_effect' => $movement->stock_effect,
                'quantity' => (float) $movement->quantity,
                'unit' => $movement->unit,
                'vendor_name' => $movement->vendor?->name,
                'internal_destination_name' => $movement->internalDestination?->name,
                'purpose_name' => $movement->purpose?->name,
                'running_balance' => $runningBalances[$materialType],
                'document_number' => $movement->document_number,
            ];
        })->values();

        return [
            'year' => $year,
            'month' => $month,
            'material_type' => $materialType,
            'rows' => $rows,
            'summary' => [
                'count' => $rows->count(),
                'latest_balances' => collect($runningBalances)->map(
                    fn (float $balance, string $type): array => ['material_type' => $type, 'balance' => round($balance, 2)]
                )->values(),
            ],
        ];
    }

    public function getAnomalyReport(int $year, ?int $month = null): array
    {
        $periods = $month ? collect([['year' => $year, 'month' => $month]]) : $this->getAvailablePeriods($year)
            ->map(fn (array $period): array => ['year' => $period['year'], 'month' => $period['month']]);

        $items = $periods->flatMap(function (array $period): array {
            $warnings = $this->getMonthlyRecap($period['year'], $period['month'])['warnings'];

            return collect($warnings)->map(fn (array $warning): array => [
                'year' => $period['year'],
                'month' => $period['month'],
                'period_label' => $this->formatPeriodLabel($period['year'], $period['month']),
                'code' => $warning['code'],
                'message' => $warning['message'],
            ])->all();
        })->values();

        return [
            'year' => $year,
            'month' => $month,
            'items' => $items,
        ];
    }

    public function getCurrentBalance(): float
    {
        $period = $this->resolveRequestedOrLatestPeriod();
        $recap = $this->getMonthlyRecap($period['year'], $period['month']);

        return $recap['closing_balance'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTpsCapacitySummary(?int $year = null, ?int $month = null): array
    {
        if ($year !== null && $month === null) {
            $latestYearPeriod = $this->getAvailablePeriods($year)->last();

            $resolvedPeriod = [
                'year' => $latestYearPeriod['year'] ?? $year,
                'month' => $latestYearPeriod['month'] ?? (int) now()->month,
                'resolved_from_latest' => $latestYearPeriod !== null,
            ];
        } else {
            $resolvedPeriod = $this->resolveRequestedOrLatestPeriod($year, $month);
        }

        $recap = $this->getMonthlyRecap($resolvedPeriod['year'], $resolvedPeriod['month']);
        $capacitySettings = $this->getTpsCapacitySettings();
        $warningThreshold = (float) ($capacitySettings['thresholds']['warning'] ?? 80.0);
        $criticalThreshold = (float) ($capacitySettings['thresholds']['critical'] ?? 95.0);

        $materials = [
            [
                'material_type' => FabaMovement::MATERIAL_FLY_ASH,
                'balance' => $recap['closing_fly_ash'],
                'capacity' => (float) ($capacitySettings['materials'][FabaMovement::MATERIAL_FLY_ASH]['capacity'] ?? 0.0),
                'warning_threshold' => (float) ($capacitySettings['materials'][FabaMovement::MATERIAL_FLY_ASH]['warning_threshold'] ?? $warningThreshold),
                'critical_threshold' => (float) ($capacitySettings['materials'][FabaMovement::MATERIAL_FLY_ASH]['critical_threshold'] ?? $criticalThreshold),
            ],
            [
                'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
                'balance' => $recap['closing_bottom_ash'],
                'capacity' => (float) ($capacitySettings['materials'][FabaMovement::MATERIAL_BOTTOM_ASH]['capacity'] ?? 0.0),
                'warning_threshold' => (float) ($capacitySettings['materials'][FabaMovement::MATERIAL_BOTTOM_ASH]['warning_threshold'] ?? $warningThreshold),
                'critical_threshold' => (float) ($capacitySettings['materials'][FabaMovement::MATERIAL_BOTTOM_ASH]['critical_threshold'] ?? $criticalThreshold),
            ],
        ];

        $materials = collect($materials)
            ->map(function (array $item): array {
                $utilization = $item['capacity'] > 0
                    ? round(($item['balance'] / $item['capacity']) * 100, 2)
                    : 0.0;

                return [
                    ...$item,
                    'utilization_percentage' => $utilization,
                    'status' => $this->resolveCapacityStatus($utilization, $item['warning_threshold'], $item['critical_threshold']),
                ];
            })
            ->values()
            ->all();

        $totalCapacity = round((float) collect($materials)->sum('capacity'), 2);
        $totalBalance = round((float) collect($materials)->sum('balance'), 2);
        $totalUtilization = $totalCapacity > 0
            ? round(($totalBalance / $totalCapacity) * 100, 2)
            : 0.0;

        return [
            'period' => [
                'year' => $resolvedPeriod['year'],
                'month' => $resolvedPeriod['month'],
                'period_label' => $this->formatPeriodLabel($resolvedPeriod['year'], $resolvedPeriod['month']),
            ],
            'materials' => $materials,
            'total' => [
                'balance' => $totalBalance,
                'capacity' => $totalCapacity,
                'utilization_percentage' => $totalUtilization,
                'status' => $this->resolveCapacityStatus($totalUtilization, $warningThreshold, $criticalThreshold),
            ],
            'thresholds' => [
                'warning' => $warningThreshold,
                'critical' => $criticalThreshold,
            ],
            'settings' => $capacitySettings['materials'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTpsCapacitySettings(): array
    {
        $config = config('faba.tps_capacity', []);
        $materials = collect($config['materials'] ?? [])
            ->mapWithKeys(fn ($capacity, string $materialType): array => [
                $materialType => [
                    'capacity' => round((float) $capacity, 2),
                    'warning_threshold' => (float) ($config['thresholds']['warning'] ?? FabaTpsCapacity::DEFAULT_WARNING_THRESHOLD),
                    'critical_threshold' => (float) ($config['thresholds']['critical'] ?? FabaTpsCapacity::DEFAULT_CRITICAL_THRESHOLD),
                ],
            ])
            ->all();

        if ($this->hasFabaTable('faba_tps_capacities')) {
            FabaTpsCapacity::query()
                ->get()
                ->each(function (FabaTpsCapacity $capacity) use (&$materials): void {
                    $materials[$capacity->material_type] = [
                        'capacity' => round((float) $capacity->capacity, 2),
                        'warning_threshold' => round((float) $capacity->warning_threshold, 2),
                        'critical_threshold' => round((float) $capacity->critical_threshold, 2),
                    ];
                });
        }

        $warningThreshold = round((float) collect($materials)->min('warning_threshold') ?: ($config['thresholds']['warning'] ?? FabaTpsCapacity::DEFAULT_WARNING_THRESHOLD), 2);
        $criticalThreshold = round((float) collect($materials)->max('critical_threshold') ?: ($config['thresholds']['critical'] ?? FabaTpsCapacity::DEFAULT_CRITICAL_THRESHOLD), 2);

        return [
            'materials' => $materials,
            'thresholds' => [
                'warning' => $warningThreshold,
                'critical' => $criticalThreshold,
            ],
        ];
    }

    public function setTpsCapacity(string $materialType, float $capacity, float $warningThreshold, float $criticalThreshold, ?string $updatedBy): FabaTpsCapacity
    {
        if (! $this->hasFabaTable('faba_tps_capacities')) {
            throw new \RuntimeException('Tabel kapasitas TPS FABA belum tersedia.');
        }

        return FabaTpsCapacity::query()->updateOrCreate(
            ['material_type' => $materialType],
            [
                'capacity' => round($capacity, 2),
                'warning_threshold' => round($warningThreshold, 2),
                'critical_threshold' => round($criticalThreshold, 2),
                'updated_by' => $updatedBy,
                'updated_at' => now(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnalysisMatrix(int $year): array
    {
        $segments = collect(config('faba.analysis_matrix.segments', []))
            ->map(function (array $segment) use ($year): array {
                $monthlyActuals = collect(range(1, 12))
                    ->map(function (int $month) use ($year, $segment): array {
                        $quantity = $this->calculateAnalysisMatrixActual($year, $month, $segment);

                        return [
                            'month' => $month,
                            'label' => $this->formatMonthLabel($month),
                            'actual_quantity' => $quantity,
                        ];
                    })
                    ->values();

                $actualQuantity = round((float) $monthlyActuals->sum('actual_quantity'), 2);
                $targetQuantity = round((float) ($segment['target_quantity'] ?? 0), 2);
                $achievement = $targetQuantity > 0
                    ? round(($actualQuantity / $targetQuantity) * 100, 2)
                    : 0.0;

                return [
                    'key' => (string) ($segment['key'] ?? Str::slug((string) ($segment['label'] ?? 'segment'))),
                    'label' => (string) ($segment['label'] ?? 'Segment'),
                    'target_quantity' => $targetQuantity,
                    'actual_quantity' => $actualQuantity,
                    'achievement_percentage' => $achievement,
                    'monthly_actuals' => $monthlyActuals->all(),
                ];
            })
            ->values();

        return [
            'year' => $year,
            'segments' => $segments->all(),
            'summary' => [
                'total_target_quantity' => round((float) $segments->sum('target_quantity'), 2),
                'total_actual_quantity' => round((float) $segments->sum('actual_quantity'), 2),
                'average_achievement_percentage' => round((float) $segments->avg('achievement_percentage'), 2),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMovementState(FabaMovement $movement): array
    {
        $periodStatus = $this->getPeriodStatus($movement->period_year, $movement->period_month);
        $locked = $this->isPeriodLocked($movement->period_year, $movement->period_month);

        return [
            'locked' => $locked,
            'effective_status' => $locked ? 'locked' : $movement->approval_status,
            'period_status' => $periodStatus,
            'period_operational_status' => $this->getPeriodMeta($movement->period_year, $movement->period_month)['operational_status'],
        ];
    }

    public function hasTransactionsForPeriod(int $year, int $month): bool
    {
        return FabaMovement::query()
            ->approved()
            ->forPeriod($year, $month)
            ->where('movement_type', '!=', FabaMovement::TYPE_OPENING_BALANCE)
            ->exists();
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
        return FabaMovement::query()
            ->approved()
            ->select(['period_year as year', 'period_month as month'])
            ->where('period_year', $year)
            ->groupBy('period_year', 'period_month')
            ->get()
            ->map(fn ($item): string => $item->year.'-'.$item->month)
            ->unique()
            ->map(function (string $period) use ($year): array {
                [, $month] = explode('-', $period);
                $month = (int) $month;
                $recap = $this->getMonthlyRecap($year, $month);
                $periodMeta = $this->getPeriodMeta($year, $month);

                return [
                    'year' => $year,
                    'month' => $month,
                    'period_label' => $this->formatPeriodLabel($year, $month),
                    'status' => $periodMeta['status'],
                    'operational_status' => $periodMeta['operational_status'],
                    'can_submit' => $periodMeta['can_submit'],
                    'can_approve' => $periodMeta['can_approve'],
                    'can_reject' => $periodMeta['can_reject'],
                    'can_review' => $periodMeta['can_review'],
                    'can_reopen' => $periodMeta['can_reopen'],
                    'recap' => $recap,
                ];
            })
            ->sortBy('month')
            ->values();
    }

    public function getPeriodMeta(int $year, int $month): array
    {
        $approval = $this->getMonthlyApproval($year, $month) ?? FabaMonthlyApproval::draftForPeriod($year, $month);
        $hasTransactions = $this->hasTransactionsForPeriod($year, $month);
        $hasPendingTransactions = FabaMovement::query()
            ->forPeriod($year, $month)
            ->pendingApproval()
            ->exists();
        $warnings = $this->getMonthlyRecap($year, $month)['warnings'];
        $operationalStatus = 'draft';

        if ($approval->status === FabaMonthlyApproval::STATUS_APPROVED) {
            $operationalStatus = 'approved';
        } elseif ($approval->status === FabaMonthlyApproval::STATUS_SUBMITTED) {
            $operationalStatus = 'submitted';
        } elseif ($hasPendingTransactions) {
            $operationalStatus = 'open';
        } elseif ($hasTransactions && count($warnings) === 0) {
            $operationalStatus = 'ready_to_submit';
        } elseif ($hasTransactions) {
            $operationalStatus = 'open';
        }

        return [
            'id' => $approval->id,
            'year' => $year,
            'month' => $month,
            'period_label' => $this->formatPeriodLabel($year, $month),
            'status' => $approval->status,
            'operational_status' => $operationalStatus,
            'rejection_note' => $approval->rejection_note,
            'submitted_at' => $approval->submitted_at?->format('Y-m-d H:i:s'),
            'approved_at' => $approval->approved_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $approval->rejected_at?->format('Y-m-d H:i:s'),
            'submitted_by_user' => $approval->submittedByUser,
            'approved_by_user' => $approval->approvedByUser,
            'rejected_by_user' => $approval->rejectedByUser,
            'can_submit' => ! $hasPendingTransactions && $approval->canSubmit(),
            'can_approve' => $approval->canApprove(),
            'can_reject' => $approval->canReject(),
            'can_review' => true,
            'can_reopen' => $approval->canReopen(),
        ];
    }

    public function getMonthlyClosingSnapshot(int $year, int $month): ?FabaMonthlyClosingSnapshot
    {
        if (! $this->hasFabaTable('faba_monthly_closing_snapshots')) {
            return null;
        }

        return FabaMonthlyClosingSnapshot::query()
            ->forPeriod($year, $month)
            ->with('approvedByUser:id,name')
            ->first();
    }

    public function storeMonthlyClosingSnapshot(int $year, int $month, ?string $approvedBy): ?FabaMonthlyClosingSnapshot
    {
        if (! $this->hasFabaTable('faba_monthly_closing_snapshots')) {
            return null;
        }

        $detail = $this->getMonthlyRecapDetail($year, $month);
        $recap = $detail['recap'];

        return FabaMonthlyClosingSnapshot::query()->updateOrCreate(
            [
                'year' => $year,
                'month' => $month,
            ],
            [
                'status' => FabaMonthlyApproval::STATUS_APPROVED,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'warning_summary' => $recap['warnings'],
                'snapshot_payload' => [
                    'period_label' => $recap['period_label'],
                    'recap' => $recap,
                    'opening_balances' => $detail['opening_balances'],
                    'vendor_breakdown' => $detail['vendor_breakdown'],
                    'internal_destination_breakdown' => $detail['internal_destination_breakdown'],
                    'purpose_breakdown' => $detail['purpose_breakdown'],
                    'movement_summary' => $recap['movement_summary'] ?? [],
                ],
            ]
        );
    }

    public function deleteMonthlyClosingSnapshot(int $year, int $month): void
    {
        if (! $this->hasFabaTable('faba_monthly_closing_snapshots')) {
            return;
        }

        FabaMonthlyClosingSnapshot::query()
            ->forPeriod($year, $month)
            ->delete();
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
            $this->getOpeningBalanceForMaterial($year, $month, FabaMovement::MATERIAL_FLY_ASH)
                + $this->getOpeningBalanceForMaterial($year, $month, FabaMovement::MATERIAL_BOTTOM_ASH),
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

    protected function resolveCapacityStatus(float $utilization, float $warningThreshold, float $criticalThreshold): string
    {
        if ($utilization >= $criticalThreshold) {
            return 'critical';
        }

        if ($utilization >= $warningThreshold) {
            return 'warning';
        }

        return 'normal';
    }

    protected function hasFabaTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function getOpeningBalanceForMaterial(int $year, int $month, string $materialType): float
    {
        $explicit = FabaMovement::query()
            ->approved()
            ->forPeriod($year, $month)
            ->where('material_type', $materialType)
            ->where('movement_type', FabaMovement::TYPE_OPENING_BALANCE)
            ->sum('quantity');

        if ($explicit > 0) {
            return round((float) $explicit, 2);
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
            + $this->sumInflows(
                FabaMovement::query()->approved()->forPeriod((int) $previousPeriod->format('Y'), (int) $previousPeriod->format('n')),
                $materialType
            )
            - $this->sumOutflows(
                FabaMovement::query()->approved()->forPeriod((int) $previousPeriod->format('Y'), (int) $previousPeriod->format('n')),
                $materialType
            ),
            2
        );
    }

    protected function hasPriorActivity(int $year, int $month, string $materialType): bool
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay()->toDateString();

        return FabaMovement::query()
            ->approved()
            ->where('material_type', $materialType)
            ->where('transaction_date', '<', $periodStart)
            ->exists();
    }

    protected function hasOpeningBalanceSource(int $year, int $month): bool
    {
        if (FabaMovement::query()->approved()->forPeriod($year, $month)->where('movement_type', FabaMovement::TYPE_OPENING_BALANCE)->exists()) {
            return true;
        }

        return ! $this->hasPriorActivity($year, $month, FabaMovement::MATERIAL_FLY_ASH)
            && ! $this->hasPriorActivity($year, $month, FabaMovement::MATERIAL_BOTTOM_ASH);
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

        $invalidExternalDocuments = FabaMovement::query()
            ->approved()
            ->forPeriod($year, $month)
            ->where('movement_type', FabaMovement::TYPE_UTILIZATION_EXTERNAL)
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

        $duplicateMovementsCount = FabaMovement::query()
            ->approved()
            ->forPeriod($year, $month)
            ->whereIn('movement_type', [
                FabaMovement::TYPE_UTILIZATION_EXTERNAL,
                FabaMovement::TYPE_UTILIZATION_INTERNAL,
                FabaMovement::TYPE_PRODUCTION,
                FabaMovement::TYPE_WORKSHOP,
                FabaMovement::TYPE_REJECT,
                FabaMovement::TYPE_DISPOSAL_POK,
            ])
            ->selectRaw('material_type, movement_type, COALESCE(vendor_id::text, internal_destination_id::text, \'\') as destination_key, COALESCE(document_number, \'\') as document_number_key, COALESCE(document_date::text, \'\') as document_date_key, quantity, COUNT(*) as duplicate_count')
            ->groupByRaw('material_type, movement_type, COALESCE(vendor_id::text, internal_destination_id::text, \'\'), COALESCE(document_number, \'\'), COALESCE(document_date::text, \'\'), quantity')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateMovementsCount > 0) {
            $warnings[] = [
                'code' => 'duplicate_warning',
                'message' => 'Terdapat transaksi dengan pola duplikasi yang perlu direview.',
            ];
        }

        return $warnings;
    }

    protected function calculateAnalysisMatrixActual(int $year, int $month, array $segment): float
    {
        $query = FabaMovement::query()
            ->approved()
            ->forPeriod($year, $month)
            ->where('stock_effect', FabaMovement::STOCK_EFFECT_OUT)
            ->with(['purpose:id,slug', 'internalDestination:id,slug']);

        if (! empty($segment['movement_types'])) {
            $query->whereIn('movement_type', $segment['movement_types']);
        }

        return round((float) $query
            ->get()
            ->filter(function (FabaMovement $movement) use ($segment): bool {
                $purposeSlugs = collect($segment['purpose_slugs'] ?? []);
                $destinationSlugs = collect($segment['internal_destination_slugs'] ?? []);

                if ($purposeSlugs->isNotEmpty() && $purposeSlugs->contains($movement->purpose?->slug)) {
                    return true;
                }

                if ($destinationSlugs->isNotEmpty() && $destinationSlugs->contains($movement->internalDestination?->slug)) {
                    return true;
                }

                return $purposeSlugs->isEmpty() && $destinationSlugs->isEmpty();
            })
            ->sum('quantity'), 2);
    }

    protected function sumMovementTypes($query, string $materialType, array $movementTypes): float
    {
        return round((float) $query
            ->where('material_type', $materialType)
            ->whereIn('movement_type', $movementTypes)
            ->sum('quantity'), 2);
    }

    protected function sumInflows($query, string $materialType): float
    {
        return round((float) $query
            ->where('material_type', $materialType)
            ->where('stock_effect', FabaMovement::STOCK_EFFECT_IN)
            ->where('movement_type', '!=', FabaMovement::TYPE_OPENING_BALANCE)
            ->sum('quantity'), 2);
    }

    protected function sumOutflows($query, string $materialType): float
    {
        return round((float) $query
            ->where('material_type', $materialType)
            ->where('stock_effect', FabaMovement::STOCK_EFFECT_OUT)
            ->sum('quantity'), 2);
    }

    protected function formatMovementDisplayNumber(FabaMovement $movement): string
    {
        $prefix = match ($movement->movement_type) {
            FabaMovement::TYPE_UTILIZATION_EXTERNAL => 'FUE',
            FabaMovement::TYPE_UTILIZATION_INTERNAL => 'FUI',
            FabaMovement::TYPE_WORKSHOP => 'FWK',
            FabaMovement::TYPE_REJECT => 'FRJ',
            FabaMovement::TYPE_DISPOSAL_POK => 'FPK',
            FabaMovement::TYPE_OPENING_BALANCE => 'FOB',
            default => 'FPR',
        };

        return $prefix.'-'.$movement->transaction_date->format('Ym').'-'.Str::upper(Str::substr((string) $movement->id, -4));
    }

    protected function getVendorBreakdown(int $year, int $month): array
    {
        return FabaMovement::query()
            ->approved()
            ->forPeriod($year, $month)
            ->where('movement_type', FabaMovement::TYPE_UTILIZATION_EXTERNAL)
            ->with('vendor:id,name')
            ->get()
            ->groupBy('vendor_id')
            ->map(fn (Collection $group): array => [
                'vendor_id' => $group->first()?->vendor_id,
                'vendor_name' => $group->first()?->vendor?->name ?? 'Tanpa Vendor',
                'quantity' => round((float) $group->sum('quantity'), 2),
            ])
            ->values()
            ->all();
    }

    protected function getInternalDestinationBreakdown(int $year, int $month): array
    {
        return FabaMovement::query()
            ->approved()
            ->forPeriod($year, $month)
            ->where('movement_type', FabaMovement::TYPE_UTILIZATION_INTERNAL)
            ->with('internalDestination:id,name')
            ->get()
            ->groupBy('internal_destination_id')
            ->map(fn (Collection $group): array => [
                'internal_destination_id' => $group->first()?->internal_destination_id,
                'internal_destination_name' => $group->first()?->internalDestination?->name ?? 'Tanpa Tujuan',
                'quantity' => round((float) $group->sum('quantity'), 2),
            ])
            ->values()
            ->all();
    }

    protected function getPurposeBreakdown(int $year, int $month): array
    {
        return FabaMovement::query()
            ->approved()
            ->forPeriod($year, $month)
            ->with('purpose:id,name')
            ->whereNotNull('purpose_id')
            ->get()
            ->groupBy('purpose_id')
            ->map(fn (Collection $group): array => [
                'purpose_id' => $group->first()?->purpose_id,
                'purpose_name' => $group->first()?->purpose?->name ?? 'Tanpa Kategori',
                'quantity' => round((float) $group->sum('quantity'), 2),
            ])
            ->values()
            ->all();
    }
}
