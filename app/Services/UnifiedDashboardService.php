<?php

namespace App\Services;

use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\User;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

class UnifiedDashboardService
{
    private int $selectedYear;

    private int $selectedMonth;

    /**
     * @var array<int, array{value: string, label: string, year: int, month: int}>
     */
    private array $availableMonths = [];

    public function __construct(
        private FabaRecapService $fabaRecapService,
        private TenantService $tenantService
    ) {}

    public function getUnifiedData(Organization $organization, ?string $requestedMonth = null): array
    {
        return $this->withinOrganizationSchema($organization, function () use ($organization, $requestedMonth): array {
            $this->availableMonths = $this->getAvailableMonthOptions();

            $resolvedMonth = $this->resolveRequestedMonth($requestedMonth);
            [$this->selectedYear, $this->selectedMonth] = array_map('intval', explode('-', $resolvedMonth));

            return [
                'waste_stats' => $this->getWasteStats(),
                'pending_approvals' => $this->getPendingApprovals(),
                'tasks' => $this->getDashboardTasks(),
                'waste_by_category' => $this->getWasteByCategory(),
                'transportation_stats' => $this->getTransportationStats(),
                'recent_activities' => $this->getRecentActivities(),
                'faba_stats' => $this->getFabaStats(),
                'faba_balance' => $this->getFabaStats()['current_balance'],
                'faba_chart' => $this->getFabaChartData(),
                'faba_pending' => $this->getFabaPendingApprovals(),
                'faba_warnings' => $this->getFabaWarnings(),
                'faba_latest' => $this->getLatestFabaPeriod(),
                'top_vendors' => $this->getTopVendors(),
                'waste_chart' => $this->getWasteChartData(),
                'faba_production_material_distribution' => $this->getFabaProductionMaterialDistribution(),
                'notification_summary' => $this->getNotificationSummary(),
                'header' => $this->getHeaderMetadata($organization),
                'filters' => [
                    'month' => $resolvedMonth,
                    'organization_id' => $organization->id,
                ],
                'available_months' => collect($this->availableMonths)
                    ->map(fn (array $month): array => [
                        'value' => $month['value'],
                        'label' => $month['label'],
                    ])
                    ->values()
                    ->all(),
                // NEW: Separate task lists for tab system
                'waste_tasks' => $this->getWasteTasks(),
                'faba_tasks' => $this->getFabaTasks(),
                'waste_pending_count' => $this->getWastePendingCount(),
                'faba_pending_count' => $this->getFabaPendingCount(),
            ];
        });
    }

    private function getWasteStats(): array
    {
        $snapshotDate = $this->snapshotDate();
        $monthStart = $snapshotDate->startOfMonth();
        $wasteRecordsUntilSnapshot = $this->wasteRecordsUntilSnapshot();
        $wasteTransportationsUntilSnapshot = $this->wasteTransportationsUntilSnapshot();

        return [
            'waste_total_records_snapshot' => (clone $wasteRecordsUntilSnapshot)->count(),
            'waste_transported_records_snapshot' => (clone $wasteTransportationsUntilSnapshot)
                ->where('status', '!=', 'cancelled')
                ->distinct('waste_record_id')
                ->count('waste_record_id'),
            'waste_untransported_records_snapshot' => $this->approvedWasteRecordsAwaitingTransportationCount(),
            'total_waste_records' => $this->wasteRecordsForSelectedMonth()->count(),
            'approved_records' => $this->wasteRecordsForSelectedMonth()->approved()->count(),
            'pending_records' => $this->wasteRecordsForSelectedMonth()->pendingApproval()->count(),
            'total_transportations' => $this->wasteTransportationsForSelectedMonth()->count(),
            'in_transit_transportations' => $this->wasteTransportationsForSelectedMonth()->inTransit()->count(),
            'expired_waste' => WasteRecord::query()
                ->approved()
                ->whereBetween('expiry_date', [$monthStart->toDateString(), $snapshotDate->toDateString()])
                ->count(),
            'expiring_soon_waste' => WasteRecord::query()
                ->approved()
                ->whereBetween('expiry_date', [
                    $snapshotDate->toDateString(),
                    $snapshotDate->addDays(7)->toDateString(),
                ])
                ->count(),
        ];
    }

    private function getPendingApprovals(): array
    {
        if (! $this->canApproveWasteRecords() && ! $this->canViewAllWasteRecords()) {
            return [];
        }

        return WasteRecord::pendingApproval()
            ->whereYear('date', $this->selectedYear)
            ->whereMonth('date', $this->selectedMonth)
            ->with(['wasteType', 'wasteType.category', 'submittedByUser'])
            ->orderBy('submitted_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'record_number' => $record->record_number,
                    'waste_type' => $record->wasteType->name,
                    'category' => $record->wasteType->category->name ?? 'N/A',
                    'quantity' => $record->quantity,
                    'unit' => $record->unit,
                    'submitted_by' => $record->submittedByUser?->name ?? 'Unknown',
                    'submitted_at' => $record->submitted_at->toIso8601String(),
                    'type' => 'waste_record',
                ];
            })->all();
    }

    private function getDashboardTasks(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $tasks = $this->isOperator($user)
            ? $this->getOperatorTasks($user)
            : $this->getApproverTasks();

        return collect($tasks)
            ->sortBy([
                ['priority_weight', 'desc'],
                ['submitted_at_timestamp', 'desc'],
            ])
            ->take(7)
            ->map(function (array $task): array {
                unset($task['priority_weight'], $task['submitted_at_timestamp']);

                return $task;
            })
            ->values()
            ->all();
    }

    private function getWasteByCategory(): array
    {
        $totalApprovedQuantity = (float) $this->wasteRecordsForSelectedMonth()
            ->approved()
            ->sum('quantity');

        return $this->wasteRecordsForSelectedMonth()
            ->approved()
            ->join('waste_types', 'waste_records.waste_type_id', '=', 'waste_types.id')
            ->join('waste_categories', 'waste_types.category_id', '=', 'waste_categories.id')
            ->selectRaw('waste_categories.name as category, SUM(waste_records.quantity) as total_quantity')
            ->groupBy('waste_categories.name')
            ->orderByDesc('total_quantity')
            ->get()
            ->map(function ($item) use ($totalApprovedQuantity) {
                return [
                    'label' => $item->category,
                    'value' => round((float) $item->total_quantity, 2),
                    'percentage' => $totalApprovedQuantity > 0
                        ? round((((float) $item->total_quantity) / $totalApprovedQuantity) * 100, 2)
                        : 0,
                ];
            })->all();
    }

    private function getTransportationStats(): array
    {
        $baseQuery = $this->wasteTransportationsForSelectedMonth();
        $total = (clone $baseQuery)->count();

        return [
            [
                'status' => 'pending',
                'count' => (clone $baseQuery)->pending()->count(),
                'color' => 'bg-yellow-500',
                'percentage' => $total > 0
                    ? ((clone $baseQuery)->pending()->count() / $total) * 100
                    : 0,
            ],
            [
                'status' => 'in_transit',
                'count' => (clone $baseQuery)->inTransit()->count(),
                'color' => 'bg-blue-500',
                'percentage' => $total > 0
                    ? ((clone $baseQuery)->inTransit()->count() / $total) * 100
                    : 0,
            ],
            [
                'status' => 'delivered',
                'count' => (clone $baseQuery)->delivered()->count(),
                'color' => 'bg-green-500',
                'percentage' => $total > 0
                    ? ((clone $baseQuery)->delivered()->count() / $total) * 100
                    : 0,
            ],
            [
                'status' => 'cancelled',
                'count' => (clone $baseQuery)->cancelled()->count(),
                'color' => 'bg-red-500',
                'percentage' => $total > 0
                    ? ((clone $baseQuery)->cancelled()->count() / $total) * 100
                    : 0,
            ],
        ];
    }

    private function getRecentActivities(): array
    {
        $activities = [];

        // Recent waste records
        $recentRecords = WasteRecord::with(['wasteType', 'createdBy'])
            ->whereYear('date', $this->selectedYear)
            ->whereMonth('date', $this->selectedMonth)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentRecords as $record) {
            $activities[] = [
                'id' => $record->id,
                'type' => 'waste_record',
                'action' => 'created',
                'description' => "Waste record {$record->record_number} created: {$record->wasteType->name} ({$record->quantity} {$record->unit})",
                'user_name' => $record->createdBy?->name ?? 'System',
                'created_at' => $record->created_at->toIso8601String(),
                'link' => "/waste-management/records/{$record->id}",
            ];
        }

        // Recent transportations
        $recentTransportations = WasteTransportation::with(['wasteRecord.wasteType', 'createdBy'])
            ->whereYear('transportation_date', $this->selectedYear)
            ->whereMonth('transportation_date', $this->selectedMonth)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentTransportations as $transportation) {
            $actions = [
                'pending' => 'scheduled',
                'in_transit' => 'dispatched',
                'delivered' => 'completed',
                'cancelled' => 'cancelled',
            ];

            $activities[] = [
                'id' => $transportation->id,
                'type' => 'transportation',
                'action' => $transportation->status === 'pending' ? 'created' : $transportation->status,
                'description' => "Transportation {$transportation->transportation_number}: {$actions[$transportation->status]}",
                'user_name' => $transportation->createdBy?->name ?? 'System',
                'created_at' => $transportation->created_at->toIso8601String(),
                'link' => "/waste-management/transportations/{$transportation->id}",
            ];
        }

        // Sort by created_at and limit to 10
        usort($activities, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($activities, 0, 10);
    }

    private function getFabaStats(): array
    {
        $monthlyRecap = $this->fabaRecapService->getMonthlyRecap($this->selectedYear, $this->selectedMonth);

        return [
            'total_production' => $monthlyRecap['total_production'],
            'total_utilization' => $monthlyRecap['total_utilization'],
            'current_balance' => $monthlyRecap['closing_balance'],
            'negative_periods' => $monthlyRecap['warning_negative_balance'] ? 1 : 0,
        ];
    }

    private function getFabaChartData(): array
    {
        $anchorDate = CarbonImmutable::create($this->selectedYear, $this->selectedMonth, 1);

        return collect(range(5, 0))
            ->map(function (int $offset) use ($anchorDate): array {
                $date = $anchorDate->subMonths($offset);
                $monthlyRecap = $this->fabaRecapService->getMonthlyRecap($date->year, $date->month);

                return [
                    'label' => $this->fabaRecapService->formatMonthLabel($date->month),
                    'month' => $date->month,
                    'year' => $date->year,
                    'production' => $monthlyRecap['total_production'],
                    'utilization' => $monthlyRecap['total_utilization'],
                    'closing_balance' => $monthlyRecap['closing_balance'],
                ];
            })
            ->values()
            ->all();
    }

    private function getFabaPendingApprovals(): array
    {
        return FabaMonthlyApproval::query()
            ->where('status', FabaMonthlyApproval::STATUS_SUBMITTED)
            ->where('year', $this->selectedYear)
            ->where('month', $this->selectedMonth)
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn (FabaMonthlyApproval $approval): array => [
                'id' => $approval->id,
                'year' => $approval->year,
                'month' => $approval->month,
                'period_label' => $this->fabaRecapService->formatPeriodLabel($approval->year, $approval->month),
                'status' => $approval->status,
                'type' => 'faba_approval',
            ])->all();
    }

    private function getApproverTasks(): array
    {
        return [
            ...$this->getApproverWasteTasks(),
            ...$this->getApproverFabaTasks(),
        ];
    }

    private function getApproverWasteTasks(): array
    {
        if (! $this->canApproveWasteRecords() && ! $this->canViewAllWasteRecords()) {
            return [];
        }

        return WasteRecord::query()
            ->pendingApproval()
            ->with(['wasteType.category', 'submittedByUser'])
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get()
            ->map(function (WasteRecord $record): array {
                return [
                    'id' => $record->id,
                    'type' => 'waste_record',
                    'task_group' => 'approval',
                    'title' => $record->record_number,
                    'subtitle' => sprintf(
                        '%s • %s • %s',
                        $record->wasteType->name,
                        $record->wasteType->category->name ?? 'N/A',
                        $this->formatQuantity($record->quantity, $record->unit)
                    ),
                    'status' => 'Pending approval',
                    'priority' => 'warning',
                    'priority_weight' => 2,
                    'age_label' => $this->formatAgeLabel($record->submitted_at?->toIso8601String()),
                    'href' => route('waste-management.records.show', $record),
                    'submitted_at_timestamp' => $record->submitted_at?->getTimestamp() ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    private function getApproverFabaTasks(): array
    {
        return FabaMonthlyApproval::query()
            ->where('status', FabaMonthlyApproval::STATUS_SUBMITTED)
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get()
            ->map(function (FabaMonthlyApproval $approval): array {
                return [
                    'id' => $approval->id,
                    'type' => 'faba_approval',
                    'task_group' => 'approval',
                    'title' => $this->fabaRecapService->formatPeriodLabel($approval->year, $approval->month),
                    'subtitle' => 'Approval FABA menunggu keputusan final untuk periode aktif.',
                    'status' => 'Submitted',
                    'priority' => 'warning',
                    'priority_weight' => 2,
                    'age_label' => $this->formatAgeLabel($approval->submitted_at?->toIso8601String()),
                    'href' => route('waste-management.faba.approvals.review', [$approval->year, $approval->month]),
                    'submitted_at_timestamp' => $approval->submitted_at?->getTimestamp() ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    private function getOperatorTasks(User $user): array
    {
        return [
            ...$this->getOperatorWasteTasks($user),
            ...$this->getOperatorFabaTasks($user),
        ];
    }

    private function getOperatorWasteTasks(User $user): array
    {
        return WasteRecord::query()
            ->byUser($user->id)
            ->whereIn('status', ['pending_review', 'rejected'])
            ->with(['wasteType.category'])
            ->orderByRaw("CASE WHEN status = 'rejected' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get()
            ->map(function (WasteRecord $record): array {
                $isRejected = $record->status === 'rejected';

                return [
                    'id' => $record->id,
                    'type' => 'waste_record',
                    'task_group' => $isRejected ? 'revision' : 'follow_up',
                    'title' => $record->record_number,
                    'subtitle' => $isRejected
                        ? sprintf(
                            'Ditolak supervisor. %s • %s',
                            $record->wasteType->name,
                            $record->rejection_reason ?: 'Perlu revisi dan submit ulang.'
                        )
                        : sprintf(
                            '%s • %s • Menunggu keputusan supervisor.',
                            $record->wasteType->name,
                            $this->formatQuantity($record->quantity, $record->unit)
                        ),
                    'status' => $isRejected ? 'Perlu revisi' : 'Menunggu review',
                    'priority' => $isRejected ? 'danger' : 'warning',
                    'priority_weight' => $isRejected ? 3 : 2,
                    'age_label' => $this->formatAgeLabel($record->submitted_at?->toIso8601String()),
                    'href' => $isRejected
                        ? route('waste-management.records.edit', $record)
                        : route('waste-management.records.show', $record),
                    'submitted_at_timestamp' => $record->submitted_at?->getTimestamp() ?? $record->updated_at?->getTimestamp() ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    private function getOperatorFabaTasks(User $user): array
    {
        return FabaMonthlyApproval::query()
            ->where('submitted_by', $user->id)
            ->whereIn('status', [FabaMonthlyApproval::STATUS_SUBMITTED, FabaMonthlyApproval::STATUS_REJECTED])
            ->orderByRaw("CASE WHEN status = 'rejected' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')
            ->limit(5)
            ->get()
            ->map(function (FabaMonthlyApproval $approval): array {
                $isRejected = $approval->status === FabaMonthlyApproval::STATUS_REJECTED;

                return [
                    'id' => $approval->id,
                    'type' => 'faba_approval',
                    'task_group' => $isRejected ? 'revision' : 'follow_up',
                    'title' => $this->fabaRecapService->formatPeriodLabel($approval->year, $approval->month),
                    'subtitle' => $isRejected
                        ? sprintf(
                            'Approval FABA ditolak. %s',
                            $approval->rejection_note ?: 'Periksa catatan revisi dan perbarui input periode.'
                        )
                        : 'Periode FABA sudah diajukan dan menunggu keputusan supervisor.',
                    'status' => $isRejected ? 'Perlu revisi' : 'Submitted',
                    'priority' => $isRejected ? 'danger' : 'warning',
                    'priority_weight' => $isRejected ? 3 : 2,
                    'age_label' => $this->formatAgeLabel($approval->submitted_at?->toIso8601String()),
                    'href' => route('waste-management.faba.approvals.review', [$approval->year, $approval->month]),
                    'submitted_at_timestamp' => $approval->submitted_at?->getTimestamp() ?? $approval->updated_at?->getTimestamp() ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    private function getFabaWarnings(): array
    {
        $monthlyRecap = $this->fabaRecapService->getMonthlyRecap($this->selectedYear, $this->selectedMonth);

        return collect($monthlyRecap['warnings'])
            ->map(fn (array $warning): array => [
                'month' => $monthlyRecap['month'],
                'period_label' => $monthlyRecap['period_label'],
                'code' => $warning['code'],
                'message' => $warning['message'],
                'closing_balance' => $monthlyRecap['closing_balance'],
            ])
            ->values()
            ->all();
    }

    private function getTopVendors(): array
    {
        $vendorRecap = $this->fabaRecapService->getVendorRecap($this->selectedYear);

        return collect($vendorRecap['vendors'])->values()->all();
    }

    private function getWasteChartData(): array
    {
        $anchorDate = CarbonImmutable::create($this->selectedYear, $this->selectedMonth, 1);

        return collect(range(5, 0))
            ->map(function (int $offset) use ($anchorDate): array {
                $date = $anchorDate->subMonths($offset);
                $year = $date->year;
                $month = $date->month;

                $recordsCreatedCount = WasteRecord::query()
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->count();

                $approvedCount = WasteRecord::query()
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->approved()
                    ->count();

                $transportDeliveredCount = WasteTransportation::query()
                    ->whereYear('transportation_date', $year)
                    ->whereMonth('transportation_date', $month)
                    ->delivered()
                    ->count();

                return [
                    'label' => $date->translatedFormat('M y'),
                    'month' => $month,
                    'year' => $year,
                    'input_count' => $recordsCreatedCount,
                    'transported_count' => $transportDeliveredCount,
                    'records_count' => $recordsCreatedCount,
                    'approved_count' => $approvedCount,
                    'transport_delivered_count' => $transportDeliveredCount,
                ];
            })
            ->values()
            ->all();
    }

    private function getFabaProductionMaterialDistribution(): array
    {
        $monthlyRecap = $this->fabaRecapService->getMonthlyRecap($this->selectedYear, $this->selectedMonth);
        $flyAshTotal = round((float) $monthlyRecap['production_fly_ash'], 2);
        $bottomAshTotal = round((float) $monthlyRecap['production_bottom_ash'], 2);
        $total = round($flyAshTotal + $bottomAshTotal, 2);

        return [
            [
                'label' => 'Fly Ash',
                'value' => $flyAshTotal,
                'percentage' => $total > 0 ? round(($flyAshTotal / $total) * 100, 2) : 0,
            ],
            [
                'label' => 'Bottom Ash',
                'value' => $bottomAshTotal,
                'percentage' => $total > 0 ? round(($bottomAshTotal / $total) * 100, 2) : 0,
            ],
        ];
    }

    private function getNotificationSummary(): array
    {
        $wasteStats = $this->getWasteStats();
        $fabaWarnings = $this->getFabaWarnings();
        $fabaPending = $this->getFabaPendingApprovals();

        return [
            'expired_waste_count' => $wasteStats['expired_waste'],
            'expiring_soon_waste_count' => $wasteStats['expiring_soon_waste'],
            'pending_waste_approvals_count' => count($this->getPendingApprovals()),
            'pending_faba_approvals_count' => count($fabaPending),
            'faba_warnings_count' => count($fabaWarnings),
            'total_count' => $wasteStats['expired_waste'] +
                            $wasteStats['expiring_soon_waste'] +
                            count($this->getPendingApprovals()) +
                            count($fabaPending) +
                            count($fabaWarnings),
        ];
    }

    private function getHeaderMetadata(Organization $organization): array
    {
        $user = Auth::user();
        $notificationSummary = $this->getNotificationSummary();
        $riskStatus = 'normal';

        if ($notificationSummary['expired_waste_count'] > 0 || $notificationSummary['faba_warnings_count'] > 0) {
            $riskStatus = 'critical';
        } elseif ($notificationSummary['expiring_soon_waste_count'] > 0 ||
                   $notificationSummary['pending_waste_approvals_count'] > 5 ||
                   $notificationSummary['pending_faba_approvals_count'] > 2) {
            $riskStatus = 'warning';
        }

        $riskLabel = match ($riskStatus) {
            'critical' => 'Kritis',
            'warning' => 'Perlu Perhatian',
            default => 'Normal',
        };

        $riskTone = match ($riskStatus) {
            'critical' => 'red',
            'warning' => 'orange',
            default => 'green',
        };

        return [
            'organization_name' => $organization->name,
            'timezone' => 'WIB',
            'current_date' => now()->format('l, j F Y'),
            'current_time' => now()->format('H:i'),
            'snapshot_month' => sprintf('%04d-%02d', $this->selectedYear, $this->selectedMonth),
            'snapshot_month_label' => $this->formatMonthOptionLabel($this->selectedYear, $this->selectedMonth),
            'risk_status' => $riskStatus,
            'risk_label' => $riskLabel,
            'risk_tone' => $riskTone,
            'user' => [
                'name' => $user?->name ?? 'Unknown User',
                'email' => $user?->email ?? '',
                'role' => $user?->role?->slug ?? 'unknown',
            ],
        ];
    }

    private function getLatestFabaPeriod(): ?array
    {
        $latestApprovedPeriod = FabaMonthlyApproval::query()
            ->where('status', FabaMonthlyApproval::STATUS_APPROVED)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        if (! $latestApprovedPeriod) {
            return null;
        }

        return [
            'id' => $latestApprovedPeriod->id,
            'year' => $latestApprovedPeriod->year,
            'month' => $latestApprovedPeriod->month,
            'status' => $latestApprovedPeriod->status,
            'period_label' => $this->fabaRecapService->formatPeriodLabel($latestApprovedPeriod->year, $latestApprovedPeriod->month),
        ];
    }

    private function canApproveWasteRecords(): bool
    {
        return Auth::user()?->hasPermission('waste_records.approve') ?? false;
    }

    private function canViewAllWasteRecords(): bool
    {
        return Auth::user()?->hasPermission('waste_records.view_all') ?? false;
    }

    private function isOperator(User $user): bool
    {
        return ! $user->isSuperAdmin() && $user->role?->slug === 'operator';
    }

    private function wasteRecordsForSelectedMonth()
    {
        return WasteRecord::query()
            ->whereYear('date', $this->selectedYear)
            ->whereMonth('date', $this->selectedMonth);
    }

    private function wasteRecordsUntilSnapshot()
    {
        return WasteRecord::query()
            ->whereDate('date', '<=', $this->snapshotDate()->toDateString());
    }

    private function wasteTransportationsForSelectedMonth()
    {
        return WasteTransportation::query()
            ->whereYear('transportation_date', $this->selectedYear)
            ->whereMonth('transportation_date', $this->selectedMonth);
    }

    private function wasteTransportationsUntilSnapshot()
    {
        return WasteTransportation::query()
            ->whereDate('transportation_date', '<=', $this->snapshotDate()->toDateString());
    }

    private function approvedWasteRecordsAwaitingTransportationCount(): int
    {
        $snapshotDate = $this->snapshotDate()->toDateString();

        return $this->wasteRecordsUntilSnapshot()
            ->approved()
            ->whereRaw(
                'COALESCE((
                    SELECT SUM(waste_transportations.quantity)
                    FROM waste_transportations
                    WHERE waste_transportations.waste_record_id = waste_records.id
                        AND waste_transportations.status != ?
                        AND waste_transportations.transportation_date <= ?
                ), 0) < waste_records.quantity',
                ['cancelled', $snapshotDate],
            )
            ->count();
    }

    /**
     * @return array<int, array{value: string, label: string, year: int, month: int}>
     */
    private function getAvailableMonthOptions(): array
    {
        $periods = collect([
            ...WasteRecord::query()
                ->selectRaw("to_char(date, 'YYYY-MM') as period")
                ->whereNotNull('date')
                ->groupByRaw("to_char(date, 'YYYY-MM')")
                ->pluck('period')
                ->all(),
            ...WasteTransportation::query()
                ->selectRaw("to_char(transportation_date, 'YYYY-MM') as period")
                ->whereNotNull('transportation_date')
                ->groupByRaw("to_char(transportation_date, 'YYYY-MM')")
                ->pluck('period')
                ->all(),
            ...FabaMovement::query()
                ->selectRaw("format('%s-%02s', period_year, period_month) as period")
                ->groupBy('period_year', 'period_month')
                ->pluck('period')
                ->all(),
        ])->filter()->unique()->sortDesc()->values();

        if ($periods->isEmpty()) {
            $currentDate = CarbonImmutable::now()->startOfMonth();

            return [[
                'value' => $currentDate->format('Y-m'),
                'label' => $this->formatMonthOptionLabel($currentDate->year, $currentDate->month),
                'year' => $currentDate->year,
                'month' => $currentDate->month,
            ]];
        }

        return $periods
            ->map(function (string $period): array {
                [$year, $month] = array_map('intval', explode('-', $period));

                return [
                    'value' => sprintf('%04d-%02d', $year, $month),
                    'label' => $this->formatMonthOptionLabel($year, $month),
                    'year' => $year,
                    'month' => $month,
                ];
            })
            ->values()
            ->all();
    }

    private function resolveRequestedMonth(?string $requestedMonth): string
    {
        $selectedMonth = collect($this->availableMonths)->firstWhere('value', $requestedMonth);

        if ($selectedMonth) {
            return $selectedMonth['value'];
        }

        return $this->availableMonths[0]['value'];
    }

    private function snapshotDate(): CarbonImmutable
    {
        $selectedDate = CarbonImmutable::create($this->selectedYear, $this->selectedMonth, 1);
        $currentMonth = CarbonImmutable::now()->startOfMonth();

        if ($selectedDate->equalTo($currentMonth)) {
            return CarbonImmutable::now();
        }

        return $selectedDate->endOfMonth();
    }

    private function formatMonthOptionLabel(int $year, int $month): string
    {
        return CarbonImmutable::create($year, $month, 1)->translatedFormat('F Y');
    }

    private function formatAgeLabel(?string $submittedAt): string
    {
        if (! $submittedAt) {
            return 'Perlu perhatian';
        }

        $submittedAtDate = CarbonImmutable::parse($submittedAt);
        $diffHours = max(0, $submittedAtDate->diffInHours(now()));

        if ($diffHours < 24) {
            return "{$diffHours} jam";
        }

        return floor($diffHours / 24).' hari';
    }

    private function formatQuantity(float|string|null $quantity, ?string $unit): string
    {
        $numeric = (float) $quantity;
        $formatted = fmod($numeric, 1.0) === 0.0
            ? number_format($numeric, 0, ',', '.')
            : number_format($numeric, 2, ',', '.');

        return trim("{$formatted} {$unit}");
    }

    private function getWasteTasks(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $tasks = $this->isOperator($user)
            ? $this->getOperatorWasteTasks($user)
            : $this->getApproverWasteTasks();

        return collect($tasks)
            ->sortBy([
                ['priority_weight', 'desc'],
                ['submitted_at_timestamp', 'desc'],
            ])
            ->take(7)
            ->map(function (array $task): array {
                unset($task['priority_weight'], $task['submitted_at_timestamp']);

                return $task;
            })
            ->values()
            ->all();
    }

    private function getFabaTasks(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $tasks = $this->isOperator($user)
            ? $this->getOperatorFabaTasks($user)
            : $this->getApproverFabaTasks();

        return collect($tasks)
            ->sortBy([
                ['priority_weight', 'desc'],
                ['submitted_at_timestamp', 'desc'],
            ])
            ->take(7)
            ->map(function (array $task): array {
                unset($task['priority_weight'], $task['submitted_at_timestamp']);

                return $task;
            })
            ->values()
            ->all();
    }

    private function getWastePendingCount(): int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return 0;
        }

        return $this->isOperator($user)
            ? WasteRecord::byUser($user->id)
                ->whereIn('status', ['pending_review', 'rejected'])
                ->count()
            : WasteRecord::pendingApproval()->count();
    }

    private function getFabaPendingCount(): int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return 0;
        }

        return $this->isOperator($user)
            ? FabaMonthlyApproval::where('submitted_by', $user->id)
                ->whereIn('status', [FabaMonthlyApproval::STATUS_SUBMITTED, FabaMonthlyApproval::STATUS_REJECTED])
                ->count()
            : FabaMonthlyApproval::where('status', FabaMonthlyApproval::STATUS_SUBMITTED)->count();
    }

    private function withinOrganizationSchema(Organization $organization, callable $callback): array
    {
        $originalSchema = $this->tenantService->getCurrentSchema();

        if (! $this->tenantService->schemaExists($organization->schema_name)) {
            abort(403);
        }

        try {
            $this->tenantService->switchToSchema($organization->schema_name);

            return $callback();
        } finally {
            if ($originalSchema && $originalSchema !== 'public') {
                $this->tenantService->switchToSchema($originalSchema);
            } else {
                $this->tenantService->switchToPublic();
            }
        }
    }
}
