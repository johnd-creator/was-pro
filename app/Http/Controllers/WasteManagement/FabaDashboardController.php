<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
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
        $tpsCapacitySummary = $this->fabaRecapService->getTpsCapacitySummary($year);
        $availablePeriods = $this->fabaRecapService->getAvailablePeriods($year);
        $latestApprovedPeriod = FabaMonthlyApproval::query()
            ->where('status', FabaMonthlyApproval::STATUS_APPROVED)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();
        $pendingApprovals = FabaMonthlyApproval::query()
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
            ])
            ->values();
        $readyClosingPeriods = $availablePeriods
            ->filter(fn (array $period): bool => $period['can_submit'])
            ->values()
            ->map(fn (array $period): array => [
                'year' => $period['year'],
                'month' => $period['month'],
                'period_label' => $period['period_label'],
                'operational_status' => $period['operational_status'],
                'can_submit' => $period['can_submit'],
                'warning_count' => count($period['recap']['warnings']),
                'closing_balance' => $period['recap']['closing_balance'],
            ]);
        $latestMovements = FabaMovement::query()
            ->with([
                'vendor:id,name',
                'internalDestination:id,name',
                'purpose:id,name',
                'createdByUser:id,name',
            ])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (FabaMovement $movement): array => [
                'id' => $movement->id,
                'display_number' => $this->formatMovementDisplayNumber($movement),
                'transaction_date' => $movement->transaction_date->format('Y-m-d'),
                'material_type' => $movement->material_type,
                'movement_type' => $movement->movement_type,
                'stock_effect' => $movement->stock_effect,
                'quantity' => (float) $movement->quantity,
                'unit' => $movement->unit,
                'period_label' => $this->fabaRecapService->formatPeriodLabel($movement->period_year, $movement->period_month),
                'vendor_name' => $movement->vendor?->name,
                'internal_destination_name' => $movement->internalDestination?->name,
                'purpose_name' => $movement->purpose?->name,
                'created_by_user' => $movement->createdByUser,
            ])
            ->values();
        $pendingTransactionApprovals = FabaMovement::query()
            ->pendingApproval()
            ->with(['createdByUser:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('submitted_at')
            ->limit(8)
            ->get()
            ->map(fn (FabaMovement $movement): array => [
                'id' => $movement->id,
                'display_number' => $this->formatMovementDisplayNumber($movement),
                'transaction_date' => $movement->transaction_date->format('Y-m-d'),
                'material_type' => $movement->material_type,
                'movement_type' => $movement->movement_type,
                'quantity' => (float) $movement->quantity,
                'unit' => $movement->unit,
                'period_label' => $this->fabaRecapService->formatPeriodLabel($movement->period_year, $movement->period_month),
                'created_by_user' => $movement->createdByUser,
            ])
            ->values();

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
            'pendingApprovals' => $pendingApprovals,
            'readyClosingPeriods' => $readyClosingPeriods,
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
            'pendingTransactionApprovals' => $pendingTransactionApprovals,
            'latestMovements' => $latestMovements,
            'topVendors' => collect($this->fabaRecapService->getVendorRecap($year)['vendors'])
                ->sortByDesc('total_quantity')
                ->take(5)
                ->values(),
            'tpsCapacitySummary' => $tpsCapacitySummary,
        ]);
    }

    protected function formatMovementDisplayNumber(FabaMovement $movement): string
    {
        return sprintf(
            'FABA-%s-%s',
            strtoupper($movement->movement_type),
            strtoupper(substr($movement->id, 0, 8))
        );
    }
}
