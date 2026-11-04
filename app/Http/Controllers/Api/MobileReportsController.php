<?php

namespace App\Http\Controllers\Api;

use App\Models\FabaMovement;
use App\Services\FabaOfficialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MobileReportsController extends ApiController
{
    public function report(Request $request, string $report, string $format): Response
    {
        abort_unless($request->user()->hasPermission('faba_reports.export'), 403);
        abort_unless(in_array($report, [
            'monthly', 'yearly', 'vendors', 'internal-destinations', 'purposes',
            'stock-card', 'anomalies', 'analysis-matrix',
        ], true) && in_array($format, ['pdf', 'xlsx'], true), 404);
        $service = app(FabaOfficialReportService::class);
        $filters = $request->only(['year', 'month', 'material_type', 'movement_type', 'vendor_id', 'internal_destination_id', 'purpose_id']);

        return $format === 'pdf'
            ? $service->downloadPdf($report, $filters)
            : $service->downloadExcel($report, $filters);
    }

    public function attachment(Request $request, string $utilization): Response
    {
        abort_unless($request->user()->hasPermission('faba_utilization.view'), 403);
        $movement = FabaMovement::query()
            ->whereIn('movement_type', FabaMovement::utilizationTypeOptions())
            ->findOrFail($utilization);
        abort_unless($movement->attachment_path && Storage::disk(config('filesystems.default'))->exists($movement->attachment_path), 404);

        $response = Storage::disk(config('filesystems.default'))->download($movement->attachment_path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
