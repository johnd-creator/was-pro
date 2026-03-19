<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Services\FabaRecapService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FabaReportsController extends Controller
{
    public function __construct(protected FabaRecapService $fabaRecapService) {}

    public function index(): Response
    {
        return Inertia::render('waste-management/faba/reports/Index', [
            'currentYear' => (int) now()->year,
        ]);
    }

    public function monthlyCsv(): StreamedResponse
    {
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);
        $recap = $this->fabaRecapService->getMonthlyRecap($year, $month);

        return response()->stream(function () use ($recap): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Metric', 'Value']);

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
            fputcsv($file, ['Month', 'Production', 'Utilization', 'Closing Balance']);

            foreach ($recap['months'] as $month) {
                fputcsv($file, [
                    $month['month'],
                    $month['total_production'],
                    $month['total_utilization'],
                    $month['closing_balance'],
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="faba-yearly-recap-'.$year.'.csv"',
        ]);
    }
}
