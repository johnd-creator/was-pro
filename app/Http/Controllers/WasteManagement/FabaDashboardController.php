<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Models\FabaMonthlyApproval;
use App\Services\FabaRecapService;
use Inertia\Inertia;
use Inertia\Response;

class FabaDashboardController extends Controller
{
    public function __construct(protected FabaRecapService $fabaRecapService) {}

    public function index(): Response
    {
        $year = (int) request('year', now()->year);
        $yearlyRecap = $this->fabaRecapService->getYearlyRecap($year);
        $latestApprovedPeriod = FabaMonthlyApproval::query()
            ->where('status', FabaMonthlyApproval::STATUS_APPROVED)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        return Inertia::render('waste-management/faba/Dashboard', [
            'year' => $year,
            'stats' => [
                'total_production' => $yearlyRecap['totals']['total_production'],
                'total_utilization' => $yearlyRecap['totals']['total_utilization'],
                'current_balance' => $this->fabaRecapService->getCurrentBalance(),
                'negative_periods' => collect($yearlyRecap['months'])
                    ->filter(fn (array $month): bool => (bool) $month['warning_negative_balance'])
                    ->count(),
            ],
            'trend' => $yearlyRecap['trend'],
            'pendingApprovals' => FabaMonthlyApproval::query()
                ->where('status', FabaMonthlyApproval::STATUS_SUBMITTED)
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(fn (FabaMonthlyApproval $approval): array => [
                    'id' => $approval->id,
                    'year' => $approval->year,
                    'month' => $approval->month,
                    'period_label' => $this->fabaRecapService->formatPeriodLabel($approval->year, $approval->month),
                    'status' => $approval->status,
                ]),
            'warnings' => collect($yearlyRecap['months'])
                ->flatMap(fn (array $month): array => collect($month['warnings'])
                    ->map(fn (array $warning): array => [
                        'month' => $month['month'],
                        'period_label' => $month['period_label'],
                        'code' => $warning['code'],
                        'message' => $warning['message'],
                        'closing_balance' => $month['closing_balance'],
                    ])->all())
                ->values(),
            'latestApprovedPeriod' => $latestApprovedPeriod ? [
                'id' => $latestApprovedPeriod->id,
                'year' => $latestApprovedPeriod->year,
                'month' => $latestApprovedPeriod->month,
                'status' => $latestApprovedPeriod->status,
                'period_label' => $this->fabaRecapService->formatPeriodLabel($latestApprovedPeriod->year, $latestApprovedPeriod->month),
            ] : null,
            'topVendors' => collect($this->fabaRecapService->getVendorRecap($year)['vendors'])
                ->sortByDesc('total_quantity')
                ->take(5)
                ->values(),
        ]);
    }
}
