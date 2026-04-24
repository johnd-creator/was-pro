<?php

namespace App\Services;

use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
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
                'waste_hauling_status_distribution' => $this->getWasteHaulingStatusDistribution(),
                'recent_activities' => $this->getRecentActivities(),
                'faba_stats' => $this->getFabaStats(),
                'faba_hero_stats' => $this->getFabaHeroStats(),
                'faba_balance' => $this->getFabaStats()['current_balance'],
                'faba_chart' => $this->getFabaChartData(),
                'faba_material_balance_distribution' => $this->getFabaMaterialBalanceDistribution(),
                'faba_utilization_distribution' => $this->getFabaUtilizationDistribution(),
                'faba_pending' => $this->getFabaPendingApprovals(),
                'faba_warnings' => $this->getFabaWarnings(),
                'faba_latest' => $this->getLatestFabaPeriod(),
                'top_vendors' => $this->getTopVendors(),
                'waste_chart' => $this->getWasteChartData(),
                'waste_backlog_urgency_distribution' => $this->getWasteBacklogUrgencyDistribution(),
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
                'hauling_attention_tasks' => $this->getHaulingAttentionTasks(),
                'waste_pending_count' => $this->getWastePendingCount(),
                'faba_pending_count' => $this->getFabaPendingCount(),
                'hauling_attention_count' => $this->getHaulingAttentionCount(),
            ];
        });
    }

    private function getWasteStats(): array
    {
        $snapshotDate = $this->snapshotDate();
        $wasteRecordsUntilSnapshot = $this->wasteRecordsUntilSnapshot();
        $wasteHaulingsUntilSnapshot = $this->wasteHaulingsUntilSnapshot();
        $wasteHaulingsForSelectedMonth = $this->wasteHaulingsForSelectedMonth();
        $backlogRecords = $this->approvedRecordsWithHaulingsUntilDate($snapshotDate)
            ->filter(fn (WasteRecord $record): bool => $this->remainingQuantityAtDate($record, $snapshotDate) > 0);

        return [
            'waste_total_records_snapshot' => (clone $wasteRecordsUntilSnapshot)->count(),
            'waste_transported_records_snapshot' => (clone $wasteHaulingsUntilSnapshot)
                ->approved()
                ->distinct('waste_record_id')
                ->count('waste_record_id'),
            'waste_untransported_records_snapshot' => $this->approvedWasteRecordsAwaitingHaulingCount(),
            'total_waste_records' => $this->wasteRecordsForSelectedMonth()->count(),
            'approved_records' => $this->wasteRecordsForSelectedMonth()->approved()->count(),
            'pending_records' => $this->wasteRecordsForSelectedMonth()->pendingApproval()->count(),
            'total_transportations' => (clone $wasteHaulingsForSelectedMonth)->count(),
            'in_transit_transportations' => (clone $wasteHaulingsForSelectedMonth)->pendingApproval()->count(),
            'expired_waste' => $backlogRecords
                ->filter(fn (WasteRecord $record): bool => $record->expiry_date !== null && $record->expiry_date->lt($snapshotDate))
                ->count(),
            'expiring_soon_waste' => $backlogRecords
                ->filter(fn (WasteRecord $record): bool => $record->expiry_date !== null && $record->expiry_date->between(
                    $snapshotDate,
                    $snapshotDate->copy()->addDays(7)->endOfDay()
                ))
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
            ->take(4)
            ->map(function (array $task): array {
                unset($task['priority_weight'], $task['submitted_at_timestamp']);

                return $task;
            })
            ->values()
            ->all();
    }

    private function getWasteHaulingStatusDistribution(): array
    {
        $snapshotDate = $this->snapshotDate()->toDateString();

        $records = WasteRecord::query()
            ->approved()
            ->whereDate('date', '<=', $snapshotDate)
            ->with([
                'haulings' => fn ($query) => $query->whereDate('hauling_date', '<=', $snapshotDate),
            ])
            ->get();

        $distribution = [
            'Belum Diangkut' => 0,
            'Menunggu Persetujuan' => 0,
            'Sebagian Diangkut' => 0,
            'Selesai' => 0,
        ];

        foreach ($records as $record) {
            $approvedQuantity = $record->getApprovedHauledQuantity();
            $reservedQuantity = $record->getReservedHaulingQuantity();
            $remainingQuantity = $record->getRemainingQuantity();

            $label = match (true) {
                $remainingQuantity <= 0 => 'Selesai',
                $reservedQuantity > $approvedQuantity => 'Menunggu Persetujuan',
                $approvedQuantity > 0 => 'Sebagian Diangkut',
                default => 'Belum Diangkut',
            };

            $distribution[$label]++;
        }

        $total = array_sum($distribution);

        return collect($distribution)
            ->filter(fn (int $value): bool => $value > 0)
            ->map(fn (int $value, string $label): array => [
                'label' => $label,
                'value' => $value,
                'percentage' => $total > 0 ? round(($value / $total) * 100, 2) : 0,
            ])
            ->values()
            ->all();
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

        // Recent haulings
        $recentHaulings = WasteHauling::with(['wasteRecord.wasteType', 'createdBy'])
            ->whereYear('hauling_date', $this->selectedYear)
            ->whereMonth('hauling_date', $this->selectedMonth)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentHaulings as $hauling) {
            $actions = [
                'pending_approval' => 'submitted',
                'approved' => 'approved',
                'rejected' => 'rejected',
                'cancelled' => 'cancelled',
            ];
            $actionLabel = $actions[$hauling->status] ?? $hauling->status;

            $activities[] = [
                'id' => $hauling->id,
                'type' => 'waste_hauling',
                'action' => $hauling->status,
                'description' => "Hauling {$hauling->hauling_number}: {$actionLabel}",
                'user_name' => $hauling->createdBy?->name ?? 'System',
                'created_at' => $hauling->created_at->toIso8601String(),
                'link' => "/waste-management/haulings/{$hauling->id}",
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

    private function getFabaHeroStats(): array
    {
        $yearlyRecap = $this->fabaRecapService->getYearlyRecap($this->selectedYear);

        return [
            'year' => $this->selectedYear,
            'total_production' => $yearlyRecap['totals']['total_production'],
            'total_utilization' => $yearlyRecap['totals']['total_utilization'],
            'current_balance' => $this->fabaRecapService->getCurrentBalance(),
            'negative_periods' => collect($yearlyRecap['months'])
                ->filter(fn (array $month): bool => (bool) $month['warning_negative_balance'])
                ->count(),
        ];
    }

    private function getFabaChartData(): array
    {
        $anchorDate = CarbonImmutable::create($this->selectedYear, $this->selectedMonth, 1);

        return collect(range(5, 0))
            ->map(function (int $offset) use ($anchorDate): array {
                $date = $anchorDate->subMonths($offset);
                $monthlyRecap = $this->fabaRecapService->getMonthlyRecap($date->year, $date->month);
                $capacitySummary = $this->fabaRecapService->getTpsCapacitySummary($date->year, $date->month);
                $hasPeriodActivity = $this->hasFabaPeriodActivity($date->year, $date->month);
                $warningCount = $hasPeriodActivity ? count($monthlyRecap['warnings']) : 0;

                return [
                    'label' => $this->fabaRecapService->formatMonthLabel($date->month),
                    'month' => $date->month,
                    'year' => $date->year,
                    'production' => $monthlyRecap['total_production'],
                    'utilization' => $monthlyRecap['total_utilization'],
                    'closing_balance' => $monthlyRecap['closing_balance'],
                    'production_fly_ash' => $monthlyRecap['production_fly_ash'],
                    'production_bottom_ash' => $monthlyRecap['production_bottom_ash'],
                    'utilization_fly_ash' => $monthlyRecap['utilization_fly_ash'],
                    'utilization_bottom_ash' => $monthlyRecap['utilization_bottom_ash'],
                    'closing_fly_ash' => $monthlyRecap['closing_fly_ash'],
                    'closing_bottom_ash' => $monthlyRecap['closing_bottom_ash'],
                    'capacity_utilization_percentage' => $capacitySummary['total']['utilization_percentage'],
                    'capacity_status' => $capacitySummary['total']['status'],
                    'capacity_warning_threshold' => $capacitySummary['thresholds']['warning'],
                    'capacity_critical_threshold' => $capacitySummary['thresholds']['critical'],
                    'warning_count' => $warningCount,
                    'has_warning' => $warningCount > 0,
                ];
            })
            ->values()
            ->all();
    }

    private function getFabaMaterialBalanceDistribution(): array
    {
        $monthlyRecap = $this->fabaRecapService->getMonthlyRecap($this->selectedYear, $this->selectedMonth);
        $distribution = [
            'Fly Ash' => max(0, (float) $monthlyRecap['closing_fly_ash']),
            'Bottom Ash' => max(0, (float) $monthlyRecap['closing_bottom_ash']),
        ];
        $total = array_sum($distribution);

        return collect($distribution)
            ->filter(fn (float $value): bool => $value > 0)
            ->map(fn (float $value, string $label): array => [
                'label' => $label,
                'value' => round($value, 2),
                'percentage' => $total > 0 ? round(($value / $total) * 100, 2) : 0,
            ])
            ->values()
            ->all();
    }

    private function getFabaUtilizationDistribution(): array
    {
        $movements = FabaMovement::query()
            ->approved()
            ->forPeriod($this->selectedYear, $this->selectedMonth);

        $external = (clone $movements)
            ->where('movement_type', FabaMovement::TYPE_UTILIZATION_EXTERNAL)
            ->sum('quantity');
        $internal = (clone $movements)
            ->where('movement_type', FabaMovement::TYPE_UTILIZATION_INTERNAL)
            ->sum('quantity');
        $distribution = [
            'Pemanfaatan Eksternal' => round((float) $external, 2),
            'Pemanfaatan Internal' => round((float) $internal, 2),
        ];
        $total = array_sum($distribution);

        return collect($distribution)
            ->filter(fn (float $value): bool => $value > 0)
            ->map(fn (float $value, string $label): array => [
                'label' => $label,
                'value' => $value,
                'percentage' => $total > 0 ? round(($value / $total) * 100, 2) : 0,
            ])
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

    private function getHaulingAttentionTasks(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $canViewAllHaulings = $user->hasPermission('waste_hauling.view_all');
        $canViewOwnHaulings = $user->hasPermission('waste_hauling.view_own');
        $canCreateHaulings = $user->hasPermission('waste_hauling.create');
        $canApproveHaulings = $user->hasPermission('waste_hauling.approve');
        $canRejectHaulings = $user->hasPermission('waste_hauling.reject');

        if (! $canViewAllHaulings && ! $canViewOwnHaulings && ! $canCreateHaulings && ! $canApproveHaulings && ! $canRejectHaulings) {
            return [];
        }

        $records = WasteRecord::query()
            ->approved()
            ->with(['wasteType.category', 'haulings'])
            ->orderBy('expiry_date')
            ->orderBy('date')
            ->get();

        if (! $canViewAllHaulings && ! $canApproveHaulings && ! $canRejectHaulings) {
            $records = $records->where('created_by', $user->id);
        }

        return $records
            ->filter(function (WasteRecord $record): bool {
                return $record->getRemainingQuantity() > 0;
            })
            ->map(function (WasteRecord $record) use ($canCreateHaulings): array {
                $expiryStatus = $record->getExpiryStatus();
                $remainingQuantity = $record->getRemainingQuantity();
                $approvedHauledQuantity = $record->getApprovedHauledQuantity();

                $status = match ($expiryStatus) {
                    'expired' => 'Expired',
                    'expiring_soon' => 'Mendekati expired',
                    default => $approvedHauledQuantity > 0 ? 'Angkut lanjutan' : 'Perlu diangkut',
                };

                $priority = match ($expiryStatus) {
                    'expired' => 'danger',
                    'expiring_soon' => 'warning',
                    default => 'info',
                };

                $priorityWeight = match ($expiryStatus) {
                    'expired' => 4,
                    'expiring_soon' => 3,
                    default => $approvedHauledQuantity > 0 ? 2 : 1,
                };

                $subtitle = sprintf(
                    '%s • %s • Sisa %s',
                    $record->wasteType->name,
                    $record->wasteType->category->name ?? 'N/A',
                    $this->formatQuantity($remainingQuantity, $record->unit)
                );

                if ($record->expiry_date) {
                    $subtitle .= sprintf(' • Batas simpan %s', $record->expiry_date->format('d M Y'));
                }

                return [
                    'id' => $record->id,
                    'type' => 'waste_hauling',
                    'task_group' => 'follow_up',
                    'title' => $record->record_number,
                    'subtitle' => $subtitle,
                    'status' => $status,
                    'priority' => $priority,
                    'priority_weight' => $priorityWeight,
                    'age_label' => $this->formatHaulingAttentionAgeLabel($record),
                    'href' => $canCreateHaulings
                        ? route('waste-management.haulings.create', ['waste_record' => $record->id])
                        : route('waste-management.haulings.index'),
                    'submitted_at_timestamp' => $record->expiry_date?->getTimestamp() ?? $record->date?->getTimestamp() ?? 0,
                ];
            })
            ->sortBy([
                ['priority_weight', 'desc'],
                ['submitted_at_timestamp', 'asc'],
            ])
            ->take(4)
            ->values()
            ->all();
    }

    private function getHaulingAttentionCount(): int
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return 0;
        }

        $canViewAllHaulings = $user->hasPermission('waste_hauling.view_all');
        $canViewOwnHaulings = $user->hasPermission('waste_hauling.view_own');
        $canCreateHaulings = $user->hasPermission('waste_hauling.create');
        $canApproveHaulings = $user->hasPermission('waste_hauling.approve');
        $canRejectHaulings = $user->hasPermission('waste_hauling.reject');

        if (! $canViewAllHaulings && ! $canViewOwnHaulings && ! $canCreateHaulings && ! $canApproveHaulings && ! $canRejectHaulings) {
            return 0;
        }

        $records = WasteRecord::query()
            ->approved()
            ->with('haulings')
            ->get();

        if (! $canViewAllHaulings && ! $canApproveHaulings && ! $canRejectHaulings) {
            $records = $records->where('created_by', $user->id);
        }

        return $records
            ->filter(fn (WasteRecord $record): bool => $record->getRemainingQuantity() > 0)
            ->count();
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
        if (! $this->hasFabaPeriodActivity($this->selectedYear, $this->selectedMonth)) {
            return [];
        }

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

    private function hasFabaPeriodActivity(int $year, int $month): bool
    {
        return FabaMovement::query()
            ->forPeriod($year, $month)
            ->exists()
            || FabaMonthlyApproval::query()
                ->where('year', $year)
                ->where('month', $month)
                ->exists();
    }

    private function getTopVendors(): array
    {
        $vendorRecap = $this->fabaRecapService->getVendorRecap($this->selectedYear);

        return collect($vendorRecap['vendors'])->values()->all();
    }

    private function getWasteChartData(): array
    {
        $anchorDate = CarbonImmutable::create($this->selectedYear, $this->selectedMonth, 1);
        $chartUnit = $this->resolveWasteChartUnit($anchorDate);

        return collect(range(5, 0))
            ->map(function (int $offset) use ($anchorDate, $chartUnit): array {
                $date = $anchorDate->subMonths($offset);
                $year = $date->year;
                $month = $date->month;
                $periodEnd = $this->periodSnapshotDate($date);
                $previousPeriodEnd = $date->startOfMonth()->subDay();

                $approvedInputCount = WasteRecord::query()
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->approved()
                    ->count();
                $approvedInputQuantity = WasteRecord::query()
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->approved()
                    ->where('unit', $chartUnit)
                    ->sum('quantity');
                $hauledQuantity = WasteHauling::query()
                    ->approved()
                    ->whereYear('hauling_date', $year)
                    ->whereMonth('hauling_date', $month)
                    ->where('unit', $chartUnit)
                    ->sum('quantity');
                $otherUnitsCount = WasteRecord::query()
                    ->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->approved()
                    ->where('unit', '!=', $chartUnit)
                    ->distinct('unit')
                    ->count('unit');

                $records = $this->approvedRecordsWithHaulingsUntilDate($periodEnd);

                $completedCount = $records
                    ->filter(function (WasteRecord $record) use ($periodEnd, $previousPeriodEnd): bool {
                        return $this->remainingQuantityAtDate($record, $previousPeriodEnd) > 0
                            && $this->remainingQuantityAtDate($record, $periodEnd) <= 0;
                    })
                    ->count();

                $closingBacklogCount = $records
                    ->filter(fn (WasteRecord $record): bool => $this->remainingQuantityAtDate($record, $periodEnd) > 0)
                    ->count();
                $unitRecords = $records
                    ->filter(fn (WasteRecord $record): bool => $record->unit === $chartUnit);
                $closingBacklogQuantity = $unitRecords
                    ->sum(fn (WasteRecord $record): float => $this->remainingQuantityAtDate($record, $periodEnd));
                $expiredBacklogQuantity = $unitRecords
                    ->filter(fn (WasteRecord $record): bool => $this->remainingQuantityAtDate($record, $periodEnd) > 0)
                    ->filter(fn (WasteRecord $record): bool => $this->backlogUrgencyLabel($record, $periodEnd) === 'Expired')
                    ->sum(fn (WasteRecord $record): float => $this->remainingQuantityAtDate($record, $periodEnd));
                $expiringSoonBacklogQuantity = $unitRecords
                    ->filter(fn (WasteRecord $record): bool => $this->remainingQuantityAtDate($record, $periodEnd) > 0)
                    ->filter(fn (WasteRecord $record): bool => $this->backlogUrgencyLabel($record, $periodEnd) === 'Mendekati Batas Simpan')
                    ->sum(fn (WasteRecord $record): float => $this->remainingQuantityAtDate($record, $periodEnd));

                return [
                    'label' => $date->translatedFormat('M y'),
                    'month' => $month,
                    'year' => $year,
                    'approved_input_count' => $approvedInputCount,
                    'completed_count' => $completedCount,
                    'closing_backlog_count' => $closingBacklogCount,
                    'approved_input_quantity' => round((float) $approvedInputQuantity, 2),
                    'hauled_quantity' => round((float) $hauledQuantity, 2),
                    'closing_backlog_quantity' => round((float) $closingBacklogQuantity, 2),
                    'expired_backlog_quantity' => round((float) $expiredBacklogQuantity, 2),
                    'expiring_soon_backlog_quantity' => round((float) $expiringSoonBacklogQuantity, 2),
                    'unit' => $chartUnit,
                    'other_units_count' => $otherUnitsCount,
                ];
            })
            ->values()
            ->all();
    }

    private function resolveWasteChartUnit(CarbonImmutable $anchorDate): string
    {
        $startDate = $anchorDate->subMonths(5)->startOfMonth()->toDateString();
        $endDate = $this->periodSnapshotDate($anchorDate)->toDateString();

        $unit = WasteRecord::query()
            ->approved()
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('unit, SUM(quantity) as total_quantity')
            ->groupBy('unit')
            ->orderByDesc('total_quantity')
            ->value('unit');

        return $unit ?: 'ton';
    }

    private function getWasteBacklogUrgencyDistribution(): array
    {
        $snapshotDate = $this->snapshotDate();
        $records = $this->approvedRecordsWithHaulingsUntilDate($snapshotDate);

        $distribution = [
            'Expired' => 0,
            'Mendekati Batas Simpan' => 0,
            'Masih Aman' => 0,
        ];

        foreach ($records as $record) {
            if ($this->remainingQuantityAtDate($record, $snapshotDate) <= 0) {
                continue;
            }

            $distribution[$this->backlogUrgencyLabel($record, $snapshotDate)]++;
        }

        $total = array_sum($distribution);

        return collect($distribution)
            ->filter(fn (int $value): bool => $value > 0)
            ->map(fn (int $value, string $label): array => [
                'label' => $label,
                'value' => $value,
                'percentage' => $total > 0 ? round(($value / $total) * 100, 2) : 0,
            ])
            ->values()
            ->all();
    }

    private function getNotificationSummary(): array
    {
        $wasteStats = $this->getWasteStats();
        $fabaWarnings = $this->getFabaWarnings();
        $criticalFabaWarnings = $this->getCriticalFabaWarnings($fabaWarnings);
        $fabaPending = $this->getFabaPendingApprovals();

        return [
            'expired_waste_count' => $wasteStats['expired_waste'],
            'expiring_soon_waste_count' => $wasteStats['expiring_soon_waste'],
            'pending_waste_approvals_count' => count($this->getPendingApprovals()),
            'pending_faba_approvals_count' => count($fabaPending),
            'faba_warnings_count' => count($criticalFabaWarnings),
            'total_count' => $wasteStats['expired_waste'] +
                            $wasteStats['expiring_soon_waste'] +
                            count($this->getPendingApprovals()) +
                            count($fabaPending) +
                            count($criticalFabaWarnings),
        ];
    }

    private function getCriticalFabaWarnings(array $warnings): array
    {
        return collect($warnings)
            ->reject(fn (array $warning): bool => ($warning['code'] ?? null) === 'missing_opening_balance')
            ->values()
            ->all();
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

    private function formatHaulingAttentionAgeLabel(WasteRecord $record): string
    {
        if (! $record->expiry_date) {
            return 'Tanpa batas simpan';
        }

        $today = now()->startOfDay();
        $expiryDate = $record->expiry_date->copy()->startOfDay();

        if ($expiryDate->lt($today)) {
            return sprintf('Lewat %d hari', $expiryDate->diffInDays($today));
        }

        if ($expiryDate->equalTo($today)) {
            return 'Batas simpan hari ini';
        }

        return sprintf('%d hari lagi', $today->diffInDays($expiryDate));
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

    private function wasteHaulingsForSelectedMonth()
    {
        return WasteHauling::query()
            ->whereYear('hauling_date', $this->selectedYear)
            ->whereMonth('hauling_date', $this->selectedMonth);
    }

    private function wasteHaulingsUntilSnapshot()
    {
        return WasteHauling::query()
            ->whereDate('hauling_date', '<=', $this->snapshotDate()->toDateString());
    }

    private function approvedWasteRecordsAwaitingHaulingCount(): int
    {
        $snapshotDate = $this->snapshotDate()->toDateString();

        return $this->wasteRecordsUntilSnapshot()
            ->approved()
            ->whereRaw(
                'COALESCE((
                    SELECT SUM(waste_haulings.quantity)
                    FROM waste_haulings
                    WHERE waste_haulings.waste_record_id = waste_records.id
                        AND waste_haulings.status = ?
                        AND waste_haulings.hauling_date <= ?
                ), 0) < waste_records.quantity',
                ['approved', $snapshotDate],
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
            ...WasteHauling::query()
                ->selectRaw("to_char(hauling_date, 'YYYY-MM') as period")
                ->whereNotNull('hauling_date')
                ->groupByRaw("to_char(hauling_date, 'YYYY-MM')")
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

    private function periodSnapshotDate(CarbonImmutable $date): CarbonImmutable
    {
        if ($date->year === $this->selectedYear && $date->month === $this->selectedMonth) {
            return $this->snapshotDate();
        }

        return $date->endOfMonth();
    }

    private function approvedRecordsWithHaulingsUntilDate(CarbonImmutable $date)
    {
        $snapshotDate = $date->toDateString();

        return WasteRecord::query()
            ->approved()
            ->whereDate('date', '<=', $snapshotDate)
            ->with([
                'haulings' => fn ($query) => $query
                    ->approved()
                    ->whereDate('hauling_date', '<=', $snapshotDate),
            ])
            ->get();
    }

    private function remainingQuantityAtDate(WasteRecord $record, CarbonImmutable $date): float
    {
        $approvedQuantity = (float) $record->haulings
            ->filter(fn (WasteHauling $hauling): bool => $hauling->hauling_date !== null && $hauling->hauling_date->lte($date))
            ->sum('quantity');

        return max(0, (float) $record->quantity - $approvedQuantity);
    }

    private function backlogUrgencyLabel(WasteRecord $record, CarbonImmutable $snapshotDate): string
    {
        if (! $record->expiry_date) {
            return 'Masih Aman';
        }

        $expiryDate = $record->expiry_date->copy()->startOfDay();
        $referenceDate = $snapshotDate->copy()->startOfDay();

        if ($expiryDate->lt($referenceDate)) {
            return 'Expired';
        }

        if ($expiryDate->lte($referenceDate->copy()->addDays(7))) {
            return 'Mendekati Batas Simpan';
        }

        return 'Masih Aman';
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
            ->take(4)
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
