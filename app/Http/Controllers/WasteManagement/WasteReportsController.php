<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\WasteReportRequest;
use App\Services\WasteReportService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class WasteReportsController extends Controller
{
    public function index(WasteReportRequest $request, WasteReportService $service): Response
    {
        return Inertia::render('waste-management/reports/Index', [
            'report' => $service->context($request->user(), $request->validated()),
            'filters' => [...$request->validated(), 'year' => (int) $request->validated('year')],
        ]);
    }

    public function balanceXlsx(WasteReportRequest $request, WasteReportService $service): BinaryFileResponse
    {
        return $service->downloadExcel('annual', $request->user(), $request->validated());
    }

    public function balancePdf(WasteReportRequest $request, WasteReportService $service): HttpResponse
    {
        return $service->downloadPdf('annual', $request->user(), $request->validated());
    }

    public function storageXlsx(WasteReportRequest $request, WasteReportService $service): BinaryFileResponse
    {
        return $service->downloadExcel('storage', $request->user(), $request->validated());
    }

    public function storagePdf(WasteReportRequest $request, WasteReportService $service): HttpResponse
    {
        return $service->downloadPdf('storage', $request->user(), $request->validated());
    }
}
