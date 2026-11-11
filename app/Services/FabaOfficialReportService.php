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
            'analysis-matrix' => $this->buildAnalysisMatrixContext($filters),
            default => abort(404),
        };
    }

    protected function buildReconciliationRows(array $recap): array
    {
        $rows = [[
            'Saldo Awal', $this->formatNumber($recap['opening_fly_ash']),
            $this->formatNumber($recap['opening_bottom_ash']), $this->formatNumber($recap['opening_balance']),
        ]];
        $labels = [
            'production' => 'Produksi', 'workshop' => 'Workshop',
            'adjustment_in' => 'Penyesuaian Masuk', 'adjustment_out' => 'Penyesuaian Keluar',
            'reject' => 'Reject', 'disposal_pok' => 'Disposal POK',
            'utilization_external' => 'Pemanfaatan Eksternal', 'utilization_internal' => 'Pemanfaatan Internal',
        ];
        foreach ($recap['balance_components'] as $component) {
            $direction = $component['stock_effect'] === 'in' ? 'Masuk (+)' : 'Keluar (-)';
            $rows[] = [
                ($labels[$component['movement_type']] ?? $component['movement_type']).' / '.$direction,
                $this->formatNumber($component['fly_ash']), $this->formatNumber($component['bottom_ash']),
                $this->formatNumber($component['total']),
            ];
        }
        if (array_key_exists('opening_reset_total', $recap)) {
            $rows[] = [
                'Reset Saldo Awal Antarbulan (+/-)', $this->formatNumber($recap['opening_reset_fly_ash']),
                $this->formatNumber($recap['opening_reset_bottom_ash']), $this->formatNumber($recap['opening_reset_total']),
            ];
        }
        $rows[] = [
            'Saldo Akhir', $this->formatNumber($recap['closing_fly_ash']),
            $this->formatNumber($recap['closing_bottom_ash']), $this->formatNumber($recap['closing_balance']),
        ];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function buildMonthlyContext(array $filters): array
    {
        if (isset($filters['version'])) {
            abort_unless(isset($filters['year'], $filters['month']) && filter_var($filters['version'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false, 422, 'Tahun, bulan, dan versi arsip wajib valid.');
        }

        $resolvedPeriod = $this->fabaRecapService->resolveRequestedOrLatestPeriod(
            isset($filters['year']) ? (int) $filters['year'] : null,
            isset($filters['month']) ? (int) $filters['month'] : null,
        );

        $year = $resolvedPeriod['year'];
        $month = $resolvedPeriod['month'];
        $version = isset($filters['version']) ? (int) $filters['version'] : null;
        $archive = $version !== null ? app(FabaClosingArchiveService::class)->find($year, $month, $version) : null;
        $recap = $archive ? ($archive->snapshot_payload['recap'] ?? []) : $this->fabaRecapService->getMonthlyRecap($year, $month);
        $provenance = $archive?->provenance ?? [];
        $label = $archive ? 'Arsip Disetujui / Versi '.$archive->version : 'Draft / Data Langsung';
        $number = fn (string $key): string => isset($recap[$key]) ? $this->formatNumber($recap[$key]) : 'Tidak tercatat';
        $completeReconciliation = isset($recap['balance_components'], $recap['opening_fly_ash'], $recap['opening_bottom_ash'], $recap['opening_balance'], $recap['closing_fly_ash'], $recap['closing_bottom_ash'], $recap['closing_balance']);

        return [
            'title' => 'Laporan Rekap Closing Bulanan FABA - '.$label,
            'sheet_title' => 'Monthly Recap',
            'file_name' => sprintf('faba-monthly-recap-%d-%02d', $year, $month).($archive ? '-v'.$archive->version : '-draft'),
            'period_label' => $recap['period_label'] ?? sprintf('%d-%02d', $year, $month),
            'reconciliation_rows' => $completeReconciliation ? $this->buildReconciliationRows($recap) : [],
            'filters' => [
                'Tahun' => (string) $year,
                'Bulan' => (string) $month,
                'Sumber Laporan' => $label,
                ...($archive ? [
                    'Organisasi saat persetujuan' => $provenance['organization_name'] ?? 'Tidak tercatat pada snapshot lama',
                    'Skema Organisasi' => $provenance['schema'] ?? 'Tidak tercatat',
                    'Disetujui oleh' => $provenance['approver_name'] ?? 'Nama tidak tercatat (ID: '.($archive->approved_by ?? '-').')',
                    'Waktu persetujuan' => $archive->approved_at?->format('Y-m-d H:i:s') ?? 'Tidak tercatat',
                    'Asal Arsip' => ($provenance['source'] ?? '') === 'legacy_snapshot' ? 'Snapshot lama, tanpa rekonstruksi' : 'Persetujuan berversi',
                ] : []),
            ],
            'summary_rows' => [
                ['label' => 'Total Produksi', 'value' => $number('total_production')],
                ['label' => 'Total Pemanfaatan', 'value' => $number('total_utilization')],
                ['label' => 'Saldo Awal', 'value' => $number('opening_balance')],
                ['label' => 'Saldo Akhir', 'value' => $number('closing_balance')],
            ],
            'table_columns' => ['Metrik', 'Nilai'],
            'table_rows' => [
                ['Produksi Fly Ash', $number('production_fly_ash')],
                ['Produksi Bottom Ash', $number('production_bottom_ash')],
                ['Pemanfaatan Fly Ash', $number('utilization_fly_ash')],
                ['Pemanfaatan Bottom Ash', $number('utilization_bottom_ash')],
                ['Opening Fly Ash', $number('opening_fly_ash')],
                ['Opening Bottom Ash', $number('opening_bottom_ash')],
                ['Closing Fly Ash', $number('closing_fly_ash')],
                ['Closing Bottom Ash', $number('closing_bottom_ash')],
                ['Jumlah Movement Produksi', (string) ($recap['production_movements_count'] ?? 'Tidak tercatat')],
                ['Jumlah Movement Pemanfaatan', (string) ($recap['utilization_movements_count'] ?? 'Tidak tercatat')],
            ],
            'warnings' => [...collect($recap['warnings'] ?? [])->pluck('message')->all(), ...($completeReconciliation ? [] : ['Rincian rekonsiliasi tidak tercatat pada snapshot ini; tidak dihitung ulang.'])],
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
            'reconciliation_rows' => $this->buildReconciliationRows($recap['totals']),
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
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function buildAnalysisMatrixContext(array $filters): array
    {
        $year = isset($filters['year']) ? (int) $filters['year'] : $this->fabaRecapService->resolveRequestedOrLatestPeriod(null, null)['year'];
        $matrix = $this->fabaRecapService->getAnalysisMatrix($year);

        return [
            'title' => 'Laporan Analysis Matrix FABA',
            'sheet_title' => 'Analysis Matrix',
            'file_name' => sprintf('faba-analysis-matrix-%d', $year),
            'period_label' => (string) $year,
            'filters' => [
                'Tahun' => (string) $year,
            ],
            'summary_rows' => [
                ['label' => 'Target Total', 'value' => $this->formatNumber($matrix['summary']['total_target_quantity'])],
                ['label' => 'Realisasi Total', 'value' => $this->formatNumber($matrix['summary']['total_actual_quantity'])],
                ['label' => 'Rata-rata Realisasi', 'value' => $this->formatNumber($matrix['summary']['average_achievement_percentage']).'%'],
            ],
            'table_columns' => ['Segmen', 'Target', 'Realisasi', '% Realisasi'],
            'table_rows' => collect($matrix['segments'])->map(fn (array $segment): array => [
                $segment['label'],
                $this->formatNumber($segment['target_quantity']),
                $this->formatNumber($segment['actual_quantity']),
                $this->formatNumber($segment['achievement_percentage']).'%',
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
