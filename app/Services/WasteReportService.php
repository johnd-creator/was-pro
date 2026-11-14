<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteRecord;
use App\Models\WasteSource;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class WasteReportService
{
    public function __construct(protected WasteBalanceService $balanceService) {}

    public function context(User $user, array $filters): array
    {
        $organization = app(OperationalInbox::class)->organization();

        return [
            ...$this->balanceService->report($user, $filters['start_date'], $filters['end_date']),
            'organization' => $organization?->name ?? 'Organisasi aktif',
            'pln_branding' => $organization && in_array($organization->id, config('waste-reports.pln_organization_ids', []), true),
            'basis' => 'Berdasarkan tanggal transaksi dan status approved saat laporan dibuat. Saldo awal + masuk - keluar = saldo akhir. Bukan snapshot persetujuan historis atau pengukuran stok fisik. Data mengikuti hak akses catatan pengguna. Kg dikonversi ke ton; satuan lain tetap dipisahkan.',
            'generated_at' => now()->timezone('Asia/Jakarta')->format('d-m-Y H:i').' WIB',
            'generated_by' => $user->name,
            'scope_label' => $user->hasPermission('waste_records.view_all') ? 'Seluruh catatan dalam organisasi aktif' : 'Catatan yang dibuat oleh pengguna ini',
        ];
    }

    public function storageReport(User $user, array $filters): array
    {
        $context = $this->context($user, $filters);
        $start = $filters['start_date'];
        $end = $filters['end_date'];
        $structuredSources = WasteSource::available();
        $records = WasteRecord::query()->approved()
            ->when($structuredSources, fn ($query) => $query->with('wasteSource'))
            ->when(! $user->hasPermission('waste_records.view_all'), fn ($query) => $query->byUser($user->id))
            ->whereDate('date', '<=', $end)
            ->with(['wasteType', 'haulings' => fn ($query) => $query->approved()->whereDate('hauling_date', '<=', $end)->orderBy('hauling_date')->orderBy('id')])
            ->orderBy('date')->orderBy('id')->get();
        $rows = [];
        $warnings = [];
        foreach ($records as $record) {
            $date = $record->date->toDateString();
            $prior = $record->haulings->filter(fn ($hauling) => $hauling->hauling_date->toDateString() < $start);
            $period = $record->haulings->filter(fn ($hauling) => $hauling->hauling_date->toDateString() >= $start);
            $remaining = (float) $record->quantity;
            foreach ($prior as $hauling) {
                if ($hauling->unit !== $record->unit) {
                    $warnings[] = $hauling->hauling_number.': satuan berbeda; saldo tidak dapat direkonsiliasi otomatis.';

                    continue;
                }
                $remaining -= (float) $hauling->quantity;
            }
            $carry = $date < $start;
            if ($carry && abs($remaining) < 0.00001 && $period->isEmpty()) {
                continue;
            }
            [$unit, $factor] = $this->displayUnit($record->unit);
            $base = [
                'record_number' => $record->record_number,
                'waste_type' => $record->wasteType?->name ?? 'Jenis tidak tersedia',
                'code' => $record->wasteType?->code ?? '-',
                'date' => $date,
                'source' => ($structuredSources ? ($record->source_name ?? $record->source) : $record->source) ?: 'Tidak tercatat',
                'expiry_date' => $record->expiry_date?->toDateString(),
                'unit' => $unit,
            ];
            $rows[] = [
                ...$base, 'kind' => $carry ? 'Saldo awal periode' : 'Limbah masuk',
                'incoming' => $carry ? 0 : round((float) $record->quantity * $factor, 4),
                'outgoing_date' => null, 'outgoing' => null, 'destination' => null, 'document_number' => null,
                'remaining' => round($remaining * $factor, 4),
            ];
            foreach ($period as $hauling) {
                $validUnit = $hauling->unit === $record->unit;
                if ($validUnit) {
                    $remaining -= (float) $hauling->quantity;
                } else {
                    $warnings[] = $hauling->hauling_number.': satuan pengangkutan '.$hauling->unit.' berbeda dari catatan '.$record->unit.'.';
                }
                if ($hauling->hauling_date->toDateString() < $date) {
                    $warnings[] = $hauling->hauling_number.': tanggal keluar mendahului tanggal masuk.';
                }
                $rows[] = [
                    ...$base, 'kind' => 'Pengangkutan', 'incoming' => null,
                    'outgoing_date' => $hauling->hauling_date->toDateString(),
                    'outgoing' => $validUnit ? round((float) $hauling->quantity * $factor, 4) : null,
                    'destination' => $hauling->destination ?: $hauling->receiver ?: 'Tidak tercatat',
                    'document_number' => $hauling->document_number ?: 'Tidak tercatat',
                    'remaining' => round($remaining * $factor, 4),
                ];
            }
            if ($remaining < 0) {
                $warnings[] = $record->record_number.': saldo negatif, perlu rekonsiliasi.';
            }
            if ($unit !== 'ton') {
                $warnings[] = 'Satuan '.$record->unit.' dipertahankan; tidak dikonversi ke ton tanpa faktor yang sah.';
            }
        }

        return [...$context, 'entries' => $rows, 'warnings' => array_values(array_unique($warnings)), 'metadata' => $filters];
    }

    public function annualReport(User $user, array $filters): array
    {
        $year = (int) $filters['year'];
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);
        $context = $this->context($user, [...$filters, 'start_date' => $start, 'end_date' => $end]);
        $rows = [];
        foreach ($context['rows'] as $row) {
            [$unit, $factor] = $this->displayUnit($row['unit']);
            $key = $row['waste_type_id'].'|'.$unit;
            $rows[$key] ??= [
                'waste_type' => $row['waste_type'], 'code' => $row['code'], 'unit' => $unit,
                'opening' => 0, 'incoming' => 0, 'outgoing' => 0, 'closing' => 0,
                'sources' => [], 'anomaly_count' => 0,
                'months' => array_fill(0, 12, ['incoming' => 0, 'outgoing' => 0]),
            ];
            foreach (['opening', 'incoming', 'outgoing', 'closing'] as $field) {
                $rows[$key][$field] += $row[$field] * $factor;
            }
            $rows[$key]['anomaly_count'] += $row['anomaly_count'];
            foreach ($row['references'] as $reference) {
                if (! empty($reference['source'])) {
                    $rows[$key]['sources'][] = $reference['source'];
                }
            }
        }
        for ($month = 1; $month <= 12; $month++) {
            $date = CarbonImmutable::create($year, $month, 1);
            $report = $this->balanceService->report($user, $date->toDateString(), $date->endOfMonth()->toDateString());
            foreach ($report['rows'] as $row) {
                [$unit, $factor] = $this->displayUnit($row['unit']);
                $key = $row['waste_type_id'].'|'.$unit;
                if (! isset($rows[$key])) {
                    continue;
                }
                $rows[$key]['months'][$month - 1]['incoming'] += $row['incoming'] * $factor;
                $rows[$key]['months'][$month - 1]['outgoing'] += $row['outgoing'] * $factor;
            }
        }
        foreach ($rows as &$row) {
            $row['sources'] = implode('; ', array_unique($row['sources'])) ?: 'Tidak tercatat';
            $available = $row['opening'] + $row['incoming'];
            $accounted = $row['outgoing'] + $row['closing'];
            $row['balance_percent'] = $available > 0 ? round($accounted / $available * 100, 2) : null;
            $row['available'] = round($available, 4);
            $row['accounted'] = round($accounted, 4);
        }
        unset($row);

        return [
            ...$context, 'annual_rows' => array_values($rows), 'year' => $year, 'metadata' => $filters,
            'warnings' => [
                'Pengangkutan approved dicatat sekali pada Keluar. Data B3 belum memisahkan pemanfaatan; kolom Manfaatkan bertanda -, artinya belum dipilah, bukan nol.',
                'Kg dikonversi ke ton (1 ton = 1.000 kg). Satuan lainnya tetap dipisahkan dan tidak dikonversi tanpa faktor yang sah.',
                'Balancing = (keluar + simpan) / (sisa tahun lalu + masuk). Jika penyebut nol, persentase tidak dihitung. Anomali tetap perlu diperiksa.',
            ],
        ];
    }

    public function downloadExcel(string $kind, User $user, array $filters): BinaryFileResponse
    {
        $report = $kind === 'storage' ? $this->storageReport($user, $filters) : $this->annualReport($user, $filters);
        $export = new class($report, $kind) implements FromView, WithEvents, WithTitle
        {
            public function __construct(private array $report, private string $kind) {}

            public function view(): View
            {
                return view('waste.reports.'.$this->kind, ['report' => $this->report, 'format' => 'excel']);
            }

            public function registerEvents(): array
            {
                return [AfterSheet::class => function (AfterSheet $event): void {
                    $sheet = $event->sheet->getDelegate();
                    $lastColumn = $this->kind === 'storage' ? 'L' : 'Z';
                    $lastRow = $sheet->getHighestRow();
                    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                        ->setPaperSize($this->kind === 'storage' ? PageSetup::PAPERSIZE_A4 : PageSetup::PAPERSIZE_A3)
                        ->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:'.$lastColumn.$lastRow);
                    $sheet->getDefaultColumnDimension()->setWidth($this->kind === 'storage' ? 14 : 11);
                    $widths = $this->kind === 'storage'
                        ? ['A' => 5, 'B' => 32, 'C' => 13, 'D' => 15, 'E' => 25, 'G' => 16, 'H' => 15, 'J' => 24, 'K' => 22, 'L' => 17]
                        : ['A' => 5, 'B' => 13, 'C' => 30, 'D' => 24, 'E' => 9, 'F' => 17, 'G' => 15, 'X' => 23, 'Z' => 24];
                    foreach ($widths as $column => $width) {
                        $sheet->getColumnDimension($column)->setWidth($width);
                    }
                    $sheet->getStyle('A1:'.$lastColumn.$lastRow)->getAlignment()->setWrapText(true)->setVertical('center');
                    $sheet->getDefaultRowDimension()->setRowHeight($this->kind === 'storage' ? 42 : 25);
                    $sheet->getRowDimension(1)->setRowHeight(75);
                    for ($row = 2; $row <= $lastRow; $row++) {
                        $values = array_filter($sheet->rangeToArray('A'.$row.':'.$lastColumn.$row, null, false, false)[0], fn ($value) => $value !== null && $value !== '');
                        if (count($values) === 1 && is_string($sheet->getCell('A'.$row)->getValue())) {
                            $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
                            $sheet->getRowDimension($row)->setRowHeight(30);
                        }
                        if (str_starts_with((string) $sheet->getCell('A'.$row)->getValue(), 'Mengetahui')) {
                            $sheet->getRowDimension($row)->setRowHeight(95);
                        }
                    }
                    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, $this->kind === 'storage' ? 8 : 6);
                    $headerStart = $this->kind === 'storage' ? 6 : 5;
                    $headerEnd = $this->kind === 'storage' ? 8 : 6;
                    $dataCount = $this->kind === 'storage' ? count($this->report['entries']) : count($this->report['annual_rows']) * 3;
                    $tableEnd = $headerEnd + max(1, $dataCount);
                    $sheet->getStyle('A'.$headerStart.':'.$lastColumn.$tableEnd)->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '333333']]],
                        'font' => ['size' => $this->kind === 'storage' ? 10 : 9],
                    ]);
                    $sheet->getStyle('A'.$headerStart.':'.$lastColumn.$headerEnd)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E4EAF0']],
                        'alignment' => ['horizontal' => 'center'],
                    ]);
                    $sheet->getStyle('A1:'.$lastColumn.'1')->getBorders()->getAllBorders()->setBorderStyle('thin');
                    if ($this->kind === 'annual') {
                        foreach (range($headerEnd + 1, $tableEnd) as $row) {
                            $color = ['FFF7C2', 'DAF2D6', 'FFE6BC'][($row - $headerEnd - 1) % 3];
                            $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->getFill()->setFillType('solid')->getStartColor()->setRGB($color);
                        }
                    }
                    $sheet->getHeaderFooter()->setOddFooter('&RHalaman &P / &N');
                }];
            }

            public function title(): string
            {
                return $this->kind === 'storage' ? 'Penyimpanan Limbah B3' : 'Neraca Limbah B3';
            }
        };

        return Excel::download($export, $this->fileName($kind, $filters).'.xlsx');
    }

    public function downloadPdf(string $kind, User $user, array $filters): Response
    {
        $report = $kind === 'storage' ? $this->storageReport($user, $filters) : $this->annualReport($user, $filters);

        $rowCount = $kind === 'storage' ? count($report['entries']) : count($report['annual_rows']) * 3;
        if ($rowCount > 150) {
            throw ValidationException::withMessages([
                'export' => 'PDF dibatasi 150 baris agar tetap dapat diproses. Gunakan Excel untuk laporan lengkap atau pilih periode yang lebih pendek untuk lembar penyimpanan.',
            ]);
        }

        $pdf = Pdf::loadView('waste.reports.'.$kind, ['report' => $report, 'format' => 'pdf'])
            ->setPaper($kind === 'annual' ? 'a3' : 'a4', 'landscape');
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 130, $canvas->get_height() - 14, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 7);

        return $pdf->download($this->fileName($kind, $filters).'.pdf');
    }

    private function displayUnit(string $unit): array
    {
        return match (strtolower(trim($unit))) {
            'kg' => ['ton', 0.001],
            'ton' => ['ton', 1],
            default => [$unit, 1],
        };
    }

    private function fileName(string $kind, array $filters): string
    {
        return $kind === 'storage'
            ? 'penyimpanan-limbah-b3-'.$filters['start_date'].'-'.$filters['end_date']
            : 'neraca-limbah-b3-'.$filters['year'];
    }
}
