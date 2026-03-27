<?php

namespace App\Services;

use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;

class UnifiedDashboardService
{
    public function __construct(
        private FabaRecapService $fabaRecapService
    ) {}

    public function getUnifiedData(): array
    {
        return [
            // Existing waste management data
            'waste_stats' => $this->getWasteStats(),
            'pending_approvals' => $this->getPendingApprovals(),
            'waste_by_category' => $this->getWasteByCategory(),
            'transportation_stats' => $this->getTransportationStats(),
            'recent_activities' => $this->getRecentActivities(),

            // FABA data (no year dependency)
            'faba_stats' => $this->getFabaStats(),
            'faba_balance' => $this->fabaRecapService->getCurrentBalance(),
            'faba_chart' => $this->getFabaChartData(),
            'faba_pending' => $this->getFabaPendingApprovals(),
            'faba_warnings' => $this->getFabaWarnings(),
            'faba_latest' => $this->getLatestFabaPeriod(),
            'top_vendors' => $this->getTopVendors(),

            // New chart data
            'waste_chart' => $this->getWasteChartData(),

            // Notifications
            'notification_summary' => $this->getNotificationSummary(),

            // Header metadata
            'header' => $this->getHeaderMetadata(),
        ];
    }

    private function getWasteStats(): array
    {
        return [
            'total_waste_records' => WasteRecord::count(),
            'approved_records' => WasteRecord::approved()->count(),
            'pending_records' => WasteRecord::pendingApproval()->count(),
            'total_transportations' => WasteTransportation::count(),
            'in_transit_transportations' => WasteTransportation::inTransit()->count(),
            'expired_waste' => WasteRecord::approved()->expired()->count(),
            'expiring_soon_waste' => WasteRecord::approved()->expiringSoon(7)->count(),
        ];
    }

    private function getPendingApprovals(): array
    {
        if (! $this->canApproveWasteRecords() && ! $this->canViewAllWasteRecords()) {
            return [];
        }

        return WasteRecord::pendingApproval()
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

    private function getWasteByCategory(): array
    {
        $stats = $this->getWasteStats();

        return WasteRecord::approved()
            ->join('waste_types', 'waste_records.waste_type_id', '=', 'waste_types.id')
            ->join('waste_categories', 'waste_types.category_id', '=', 'waste_categories.id')
            ->selectRaw('waste_categories.name as category, COUNT(*) as count')
            ->groupBy('waste_categories.name')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) use ($stats) {
                return [
                    'category' => $item->category,
                    'count' => $item->count,
                    'percentage' => $stats['total_waste_records'] > 0
                        ? ($item->count / $stats['total_waste_records']) * 100
                        : 0,
                ];
            })->all();
    }

    private function getTransportationStats(): array
    {
        $stats = $this->getWasteStats();
        $total = $stats['total_transportations'];

        return [
            [
                'status' => 'pending',
                'count' => WasteTransportation::pending()->count(),
                'color' => 'bg-yellow-500',
                'percentage' => $total > 0
                    ? (WasteTransportation::pending()->count() / $total) * 100
                    : 0,
            ],
            [
                'status' => 'in_transit',
                'count' => WasteTransportation::inTransit()->count(),
                'color' => 'bg-blue-500',
                'percentage' => $total > 0
                    ? (WasteTransportation::inTransit()->count() / $total) * 100
                    : 0,
            ],
            [
                'status' => 'delivered',
                'count' => WasteTransportation::delivered()->count(),
                'color' => 'bg-green-500',
                'percentage' => $total > 0
                    ? (WasteTransportation::delivered()->count() / $total) * 100
                    : 0,
            ],
            [
                'status' => 'cancelled',
                'count' => WasteTransportation::cancelled()->count(),
                'color' => 'bg-red-500',
                'percentage' => $total > 0
                    ? (WasteTransportation::cancelled()->count() / $total) * 100
                    : 0,
            ],
        ];
    }

    private function getRecentActivities(): array
    {
        $activities = [];

        // Recent waste records
        $recentRecords = WasteRecord::with(['wasteType', 'createdBy'])
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
        $period = $this->fabaRecapService->getLatestAvailablePeriod();
        $year = $period['year'] ?? now()->year;
        $yearlyRecap = $this->fabaRecapService->getYearlyRecap($year);

        return [
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
        $latestPeriod = $this->fabaRecapService->getLatestAvailablePeriod();
        $anchorDate = $latestPeriod
            ? CarbonImmutable::create($latestPeriod['year'], $latestPeriod['month'], 1)
            : CarbonImmutable::now()->startOfMonth();

        return collect(range(5, 0))
            ->reverse()
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
        return \App\Models\FabaMonthlyApproval::query()
            ->where('status', \App\Models\FabaMonthlyApproval::STATUS_SUBMITTED)
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn (\App\Models\FabaMonthlyApproval $approval): array => [
                'id' => $approval->id,
                'year' => $approval->year,
                'month' => $approval->month,
                'period_label' => $this->fabaRecapService->formatPeriodLabel($approval->year, $approval->month),
                'status' => $approval->status,
                'type' => 'faba_approval',
            ])->all();
    }

    private function getFabaWarnings(): array
    {
        $period = $this->fabaRecapService->getLatestAvailablePeriod();
        $year = $period['year'] ?? now()->year;
        $yearlyRecap = $this->fabaRecapService->getYearlyRecap($year);

        return collect($yearlyRecap['months'])
            ->flatMap(fn (array $month): array => collect($month['warnings'])
                ->map(fn (array $warning): array => [
                    'month' => $month['month'],
                    'period_label' => $month['period_label'],
                    'code' => $warning['code'],
                    'message' => $warning['message'],
                    'closing_balance' => $month['closing_balance'],
                ])->all())
            ->values()
            ->all();
    }

    private function getTopVendors(): array
    {
        $period = $this->fabaRecapService->getLatestAvailablePeriod();
        $year = $period['year'] ?? now()->year;
        $vendorRecap = $this->fabaRecapService->getVendorRecap($year);

        return collect($vendorRecap['vendors'])->values()->all();
    }

    private function getWasteChartData(): array
    {
        $anchorDate = $this->resolveWasteChartAnchorDate();

        return collect(range(5, 0))
            ->reverse()
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
                    'records_count' => $recordsCreatedCount,
                    'approved_count' => $approvedCount,
                    'transport_delivered_count' => $transportDeliveredCount,
                ];
            })
            ->values()
            ->all();
    }

    private function resolveWasteChartAnchorDate(): CarbonImmutable
    {
        $latestWasteRecordDate = WasteRecord::query()->max('date');
        $latestTransportationDate = WasteTransportation::query()->max('transportation_date');

        $dates = collect([$latestWasteRecordDate, $latestTransportationDate])
            ->filter()
            ->map(fn (string $date): CarbonImmutable => CarbonImmutable::parse($date)->startOfMonth())
            ->sort()
            ->values();

        return $dates->last() ?? CarbonImmutable::now()->startOfMonth();
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

    private function getHeaderMetadata(): array
    {
        $user = Auth::user();
        $organization = $user?->organization;

        // Determine risk status based on notification summary
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
            'organization_name' => $organization?->name ?? 'Unknown Organization',
            'timezone' => 'WIB',
            'current_date' => now()->format('l, j F Y'),
            'current_time' => now()->format('H:i'),
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
        $latestApprovedPeriod = \App\Models\FabaMonthlyApproval::query()
            ->where('status', \App\Models\FabaMonthlyApproval::STATUS_APPROVED)
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
}
