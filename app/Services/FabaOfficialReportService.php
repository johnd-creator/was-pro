<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class FabaOfficialReportService
{
    public function __construct(protected FabaRecapService $fabaRecapService) {}

    public function downloadExcel(string $reportKey, array $filters = []): BinaryFileResponse
    {
        $context = $this->buildContext($reportKey, $filters);

        return Excel::download($this->makeExcelExport($context), $context['file_name'].'.xlsx');
    }

    public function downloadPdf(string $reportKey, array $filters = []): Response
    {
        $context = $this->buildContext($reportKey, $filters);

        return Pdf::loadView('faba.reports.official', [
            ...$context,
            'format' => 'pdf',
        ])->setPaper('a4', 'landscape')->download($context['file_name'].'.pdf');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildContext(string $reportKey, array $filters = []): array
    {
        return match ($reportKey) {
            'monthly' => $this->buildMonthlyContext($filters),
            'yearly' => $this->buildYearlyContext($filters),
            'vendors' => $this->buildVendorContext($filters),
            'internal-destinations' => $this->buildInternalDestinationContext($filters),
            'purposes' => $this->buildPurposeContext($filters),
            'stock-card' => $this->buildStockCardContext($filters),
            'anomalies' => $this->buildAnomalyContext($filters),
            default => abort(404),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function buildMonthlyContext(array $filters): array
    {
        $resolvedPeriod = $this->fabaRecapService->resolveRequestedOrLatestPeriod(
            isset($filters['year']) ? (int) $filters['year'] : null,
            isset($filters['month']) ? (int) $filters['month'] : null,
        );

        $year = $resolvedPeriod['year'];
        $month = $resolvedPeriod['month'];
        $recap = $this->fabaRecapService->getMonthlyRecap($year, $month);

        return [
            'title' => 'Laporan Rekap Closing Bulanan FABA',
            'sheet_title' => 'Monthly Recap',
            'file_name' => sprintf('faba-monthly-recap-%d-%02d', $year, $month),
            'period_label' => $recap['period_label'],
            'filters' => [
                'Tahun' => (string) $year,
                'Bulan' => (string) $month,
            ],
            'summary_rows' => [
                ['label' => 'Total Produksi', 'value' => $this->formatNumber($recap['total_production'])],
                ['label' => 'Total Pemanfaatan', 'value' => $this->formatNumber($recap['total_utilization'])],
                ['label' => 'Saldo Awal', 'value' => $this->formatNumber($recap['opening_balance'])],
                ['label' => 'Saldo Akhir', 'value' => $this->formatNumber($recap['closing_balance'])],
            ],
            'table_columns' => ['Metrik', 'Nilai'],
            'table_rows' => [
                ['Produksi Fly Ash', $this->formatNumber($recap['production_fly_ash'])],
                ['Produksi Bottom Ash', $this->formatNumber($recap['production_bottom_ash'])],
                ['Pemanfaatan Fly Ash', $this->formatNumber($recap['utilization_fly_ash'])],
                ['Pemanfaatan Bottom Ash', $this->formatNumber($recap['utilization_bottom_ash'])],
                ['Opening Fly Ash', $this->formatNumber($recap['opening_fly_ash'])],
                ['Opening Bottom Ash', $this->formatNumber($recap['opening_bottom_ash'])],
                ['Closing Fly Ash', $this->formatNumber($recap['closing_fly_ash'])],
                ['Closing Bottom Ash', $this->formatNumber($recap['closing_bottom_ash'])],
                ['Jumlah Movement Produksi', (string) $recap['production_movements_count']],
                ['Jumlah Movement Pemanfaatan', (string) $recap['utilization_movements_count']],
            ],
            'warnings' => collect($recap['warnings'])->pluck('message')->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function buildYearlyContext(array $filters): array
    {
        $year = isset($filters['year']) ? (int) $filters['year'] : $this->fabaRecapService->resolveRequestedOrLatestPeriod(null, null)['year'];
        $recap = $this->fabaRecapService->getYearlyRecap($year);

        return [
            'title' => 'Laporan Rekap Tahunan FABA',
            'sheet_title' => 'Yearly Recap',
            'file_name' => sprintf('faba-yearly-recap-%d', $year),
            'period_label' => (string) $year,
            'filters' => [
                'Tahun' => (string) $year,
            ],
            'summary_rows' => [
                ['label' => 'Total Produksi', 'value' => $this->formatNumber($recap['totals']['total_production'])],
                ['label' => 'Total Pemanfaatan', 'value' => $this->formatNumber($recap['totals']['total_utilization'])],
                ['label' => 'Saldo Akhir', 'value' => $this->formatNumber($recap['totals']['closing_balance'])],
            ],
            'table_columns' => ['Periode', 'Produksi', 'Pemanfaatan', 'Saldo Akhir', 'Warnings'],
            'table_rows' => collect($recap['months'])->map(fn (array $month): array => [
                $month['period_label'],
                $this->formatNumber($month['total_production']),
                $this->formatNumber($month['total_utilization']),
                $this->formatNumber($month['closing_balance']),
                collect($month['warnings'])->pluck('message')->implode(' | '),
            ])->all(),
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function buildVendorContext(array $filters): array
    {
        $year = isset($filters['year']) ? (int) $filters['year'] : $this->fabaRecapService->resolveRequestedOrLatestPeriod(null, null)['year'];
        $vendorId = $filters['vendor_id'] ?? null;
        $recap = $this->fabaRecapService->getVendorRecap($year, $vendorId);

        return [
            'title' => 'Laporan Rekap Vendor FABA',
            'sheet_title' => 'Vendor Recap',
            'file_name' => sprintf('faba-vendors-%d', $year),
            'period_label' => (string) $year,
            'filters' => [
                'Tahun' => (string) $year,
                'Vendor' => $vendorId ?: 'Semua',
            ],
            'summary_rows' => [
                ['label' => 'Jumlah Vendor', 'value' => (string) count($recap['vendors'])],
            ],
            'table_columns' => ['Vendor', 'Total Quantity', 'Transactions', 'Materials'],
            'table_rows' => collect($recap['vendors'])->map(fn (array $vendor): array => [
                $vendor['vendor_name'],
                $this->formatNumber($vendor['total_quantity']),
                (string) $vendor['transactions_count'],
                implode(', ', $vendor['materials']),
            ])->all(),
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function buildInternalDestinationContext(array $filters): array
    {
        $year = isset($filters['year']) ? (int) $filters['year'] : $this->fabaRecapService->resolveRequestedOrLatestPeriod(null, null)['year'];
        $destinationId = $filters['internal_destination_id'] ?? null;
        $recap = $this->fabaRecapService->getInternalDestinationRecap($year, $destinationId);

        return [
            'title' => 'Laporan Tujuan Internal FABA',
            'sheet_title' => 'Internal Destinations',
            'file_name' => sprintf('faba-internal-destinations-%d', $year),
            'period_label' => (string) $year,
            'filters' => [
                'Tahun' => (string) $year,
                'Tujuan Internal' => $destinationId ?: 'Semua',
            ],
            'summary_rows' => [
                ['label' => 'Jumlah Tujuan Internal', 'value' => (string) count($recap['destinations'])],
            ],
            'table_columns' => ['Tujuan Internal', 'Total Quantity', 'Transactions', 'Materials'],
            'table_rows' => collect($recap['destinations'])->map(fn (array $destination): array => [
                $destination['internal_destination_name'],
                $this->formatNumber($destination['total_quantity']),
                (string) $destination['transactions_count'],
                implode(', ', $destination['materials']),
            ])->all(),
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function buildPurposeContext(array $filters): array
    {
        $year = isset($filters['year']) ? (int) $filters['year'] : $this->fabaRecapService->resolveRequestedOrLatestPeriod(null, null)['year'];
        $purposeId = $filters['purpose_id'] ?? null;
        $recap = $this->fabaRecapService->getPurposeRecap($year, $purposeId);

        return [
            'title' => 'Laporan Purpose / Use-case FABA',
            'sheet_title' => 'Purposes',
            'file_name' => sprintf('faba-purposes-%d', $year),
            'period_label' => (string) $year,
            'filters' => [
                'Tahun' => (string) $year,
                'Purpose' => $purposeId ?: 'Semua',
            ],
            'summary_rows' => [
                ['label' => 'Jumlah Purpose', 'value' => (string) count($recap['purposes'])],
            ],
            'table_columns' => ['Purpose', 'Total Quantity', 'Transactions', 'Materials'],
            'table_rows' => collect($recap['purposes'])->map(fn (array $purpose): array => [
                $purpose['purpose_name'],
                $this->formatNumber($purpose['total_quantity']),
                (string) $purpose['transactions_count'],
                implode(', ', $purpose['materials']),
            ])->all(),
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function buildStockCardContext(array $filters): array
    {
        $year = isset($filters['year']) ? (int) $filters['year'] : $this->fabaRecapService->resolveRequestedOrLatestPeriod(null, null)['year'];
        $month = isset($filters['month']) && $filters['month'] !== null ? (int) $filters['month'] : null;
        $materialType = $filters['material_type'] ?? null;
        $stockCard = $this->fabaRecapService->getStockCard($year, $month, $materialType);

        return [
            'title' => 'Laporan Stock Card FABA',
            'sheet_title' => 'Stock Card',
            'file_name' => sprintf('faba-stock-card-%d-%s', $year, $month ?: 'all'),
            'period_label' => $month ? $this->fabaRecapService->formatPeriodLabel($year, $month) : (string) $year,
            'filters' => [
                'Tahun' => (string) $year,
                'Bulan' => $month ? (string) $month : 'Semua',
                'Material' => $materialType ?: 'Semua',
            ],
            'summary_rows' => [
                ['label' => 'Jumlah Movement', 'value' => (string) $stockCard['summary']['count']],
                ['label' => 'Saldo Aktif', 'value' => collect($stockCard['summary']['latest_balances'])
                    ->map(fn (array $item): string => sprintf('%s %s', $item['material_type'], $this->formatNumber($item['balance'])))
                    ->implode(' | ')],
            ],
            'table_columns' => ['Tanggal', 'Nomor', 'Material', 'Movement Type', 'Stock Effect', 'Qty', 'Running Balance', 'Referensi'],
            'table_rows' => collect($stockCard['rows'])->map(fn (array $row): array => [
                $row['transaction_date'],
                $row['display_number'],
                $row['material_type'],
                $row['movement_type'],
                $row['stock_effect'],
                $this->formatNumber($row['quantity']),
                $this->formatNumber($row['running_balance']),
                $row['vendor_name'] ?? $row['internal_destination_name'] ?? $row['purpose_name'] ?? $row['document_number'] ?? '',
            ])->all(),
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function buildAnomalyContext(array $filters): array
    {
        $resolvedPeriod = $this->fabaRecapService->resolveRequestedOrLatestPeriod(
            isset($filters['year']) ? (int) $filters['year'] : null,
            isset($filters['month']) ? (int) $filters['month'] : null,
        );

        $year = $resolvedPeriod['year'];
        $month = $resolvedPeriod['month'];
        $report = $this->fabaRecapService->getAnomalyReport($year, $month);

        return [
            'title' => 'Laporan Anomali FABA',
            'sheet_title' => 'Anomalies',
            'file_name' => sprintf('faba-anomalies-%d-%02d', $year, $month),
            'period_label' => $this->fabaRecapService->formatPeriodLabel($year, $month),
            'filters' => [
                'Tahun' => (string) $year,
                'Bulan' => (string) $month,
            ],
            'summary_rows' => [
                ['label' => 'Jumlah Anomali', 'value' => (string) count($report['items'])],
            ],
            'table_columns' => ['Periode', 'Code', 'Message'],
            'table_rows' => collect($report['items'])->map(fn (array $item): array => [
                $item['period_label'],
                $item['code'],
                $item['message'],
            ])->all(),
            'warnings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function makeExcelExport(array $context): object
    {
        return new class($context) implements FromView, WithTitle
        {
            /**
             * @param  array<string, mixed>  $context
             */
            public function __construct(private array $context) {}

            public function view(): View
            {
                return view('faba.reports.official', [
                    ...$this->context,
                    'format' => 'excel',
                ]);
            }

            public function title(): string
            {
                return Str::limit($this->context['sheet_title'] ?? 'FABA Report', 31, '');
            }
        };
    }

    protected function formatNumber(float|int|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}
