<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Models\FabaProductionEntry;
use App\Models\FabaUtilizationEntry;
use App\Models\Vendor;
use App\Services\FabaRecapService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FabaReportsController extends Controller
{
    public function __construct(protected FabaRecapService $fabaRecapService) {}

    public function index(): Response
    {
        $resolvedPeriod = $this->fabaRecapService->resolveRequestedOrLatestPeriod(
            request()->filled('year') ? (int) request('year') : null,
            request()->filled('month') ? (int) request('month') : null,
        );
        $year = $resolvedPeriod['year'];
        $month = $resolvedPeriod['month'];
        $materialType = request('material_type');
        $entryType = request('entry_type');
        $utilizationType = request('utilization_type');
        $vendorId = request('vendor_id');
        $monthlyRecap = $this->fabaRecapService->getMonthlyRecap($year, $month);
        $yearlyRecap = $this->fabaRecapService->getYearlyRecap($year);

        return Inertia::render('waste-management/faba/reports/Index', [
            'currentYear' => (int) now()->year,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'material_type' => $materialType,
                'entry_type' => $entryType,
                'utilization_type' => $utilizationType,
                'vendor_id' => $vendorId,
            ],
            'availablePeriods' => $this->fabaRecapService->getAvailablePeriodOptions(),
            'resolvedFromLatestPeriod' => $resolvedPeriod['resolved_from_latest'],
            'options' => [
                'materials' => FabaProductionEntry::materialOptions(),
                'entryTypes' => FabaProductionEntry::entryTypeOptions(),
                'utilizationTypes' => FabaUtilizationEntry::utilizationTypeOptions(),
                'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            ],
            'monthlyRecap' => $monthlyRecap,
            'yearlyRecap' => [
                'year' => $yearlyRecap['year'],
                'totals' => $yearlyRecap['totals'],
                'latest_month' => $yearlyRecap['months'][max(0, $month - 1)] ?? null,
            ],
        ]);
    }

    public function monthlyCsv(): StreamedResponse
    {
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);
        $recap = $this->fabaRecapService->getMonthlyRecap($year, $month);

        return response()->stream(function () use ($recap): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                Log::error('Failed to open CSV stream for FABA monthly recap export.');
                abort(500, 'Gagal menyiapkan file export rekap bulanan FABA.');
            }

            fputcsv($file, ['Metric', 'Value']);
            fputcsv($file, ['period_label', $recap['period_label']]);

            foreach ([
                'production_fly_ash',
                'production_bottom_ash',
                'utilization_fly_ash',
                'utilization_bottom_ash',
                'total_production',
                'total_utilization',
                'opening_balance',
                'closing_balance',
            ] as $metric) {
                fputcsv($file, [$metric, $recap[$metric]]);
            }

            foreach ($recap['warnings'] as $warning) {
                fputcsv($file, ['warning:'.$warning['code'], $warning['message']]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="faba-monthly-recap-'.$year.'-'.$month.'.csv"',
        ]);
    }

    public function yearlyCsv(): StreamedResponse
    {
        $year = (int) request('year', now()->year);
        $recap = $this->fabaRecapService->getYearlyRecap($year);

        return response()->stream(function () use ($recap): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                Log::error('Failed to open CSV stream for FABA yearly recap export.');
                abort(500, 'Gagal menyiapkan file export rekap tahunan FABA.');
            }

            fputcsv($file, ['Period', 'Production', 'Utilization', 'Closing Balance', 'Warnings']);

            foreach ($recap['months'] as $month) {
                fputcsv($file, [
                    $month['period_label'],
                    $month['total_production'],
                    $month['total_utilization'],
                    $month['closing_balance'],
                    collect($month['warnings'])->pluck('message')->implode(' | '),
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="faba-yearly-recap-'.$year.'.csv"',
        ]);
    }
}
