<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\WasteBalanceRequest;
use App\Services\CsvExportService;
use App\Services\WasteBalanceService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WasteBalanceController extends Controller
{
    public function index(WasteBalanceRequest $request, WasteBalanceService $service): Response
    {
        return Inertia::render('waste-management/records/Balance', [
            'report' => $service->report($request->user(), $request->validated('start_date'), $request->validated('end_date')),
        ]);
    }

    public function exportCsv(WasteBalanceRequest $request, WasteBalanceService $service): StreamedResponse
    {
        $report = $service->report($request->user(), $request->validated('start_date'), $request->validated('end_date'));

        return response()->streamDownload(function () use ($report): void {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            $write = function (array $values) use ($file): void {
                CsvExportService::writeRow($file, $values);
            };
            $write(['Neraca Operasional Limbah', $report['start_date'], $report['end_date']]);
            $write(['Dasar perhitungan', $report['basis']]);
            $write(['Jenis limbah', 'Kode', 'Satuan', 'Saldo awal', 'Masuk', 'Keluar', 'Saldo akhir', 'Jumlah anomali']);
            foreach ($report['rows'] as $row) {
                $write([$row['waste_type'], $row['code'], $row['unit'], $row['opening'], $row['incoming'], $row['outgoing'], $row['closing'], $row['anomaly_count']]);
            }
            $write([]);
            $write(['Total per satuan', 'Satuan', 'Saldo awal', 'Masuk', 'Keluar', 'Saldo akhir']);
            foreach ($report['totals'] as $total) {
                $write(['Total', $total['unit'], $total['opening'], $total['incoming'], $total['outgoing'], $total['closing']]);
            }
            $write([]);
            $write(['Referensi catatan', 'Tanggal catatan', 'Asal limbah', 'Jenis limbah', 'Satuan', 'Saldo awal', 'Masuk', 'Keluar', 'Saldo akhir', 'Anomali']);
            foreach ($report['rows'] as $row) {
                foreach ($row['references'] as $reference) {
                    $write([$reference['record_number'], $reference['date'], $reference['source'], $row['waste_type'], $row['unit'], $reference['opening'], $reference['incoming'], $reference['outgoing'], $reference['closing'], implode(' | ', $reference['anomalies'])]);
                }
            }
            fclose($file);
        }, 'neraca-limbah-'.$report['start_date'].'-'.$report['end_date'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
