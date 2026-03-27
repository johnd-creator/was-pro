<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Models\FabaInternalDestination;
use App\Models\FabaMovement;
use App\Models\FabaPurpose;
use App\Models\Vendor;
use App\Services\FabaOfficialReportService;
use App\Services\FabaRecapService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FabaReportsController extends Controller
{
    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaOfficialReportService $fabaOfficialReportService
    ) {}

    public function index(): Response
    {
        $resolvedPeriod = $this->fabaRecapService->resolveRequestedOrLatestPeriod(
            request()->filled('year') ? (int) request('year') : null,
            request()->filled('month') ? (int) request('month') : null,
        );
        $year = $resolvedPeriod['year'];
        $month = $resolvedPeriod['month'];
        $materialType = request('material_type');
        $movementType = request('movement_type');
        $vendorId = request('vendor_id');
        $internalDestinationId = request('internal_destination_id');
        $purposeId = request('purpose_id');
        $monthlyRecap = $this->fabaRecapService->getMonthlyRecap($year, $month);
        $yearlyRecap = $this->fabaRecapService->getYearlyRecap($year);

        return Inertia::render('waste-management/faba/reports/Index', [
            'currentYear' => (int) now()->year,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'material_type' => $materialType,
                'movement_type' => $movementType,
                'vendor_id' => $vendorId,
                'internal_destination_id' => $internalDestinationId,
                'purpose_id' => $purposeId,
            ],
            'availablePeriods' => $this->fabaRecapService->getAvailablePeriodOptions(),
            'resolvedFromLatestPeriod' => $resolvedPeriod['resolved_from_latest'],
            'options' => [
                'materials' => FabaMovement::materialOptions(),
                'movementTypes' => array_values(array_filter(
                    FabaMovement::movementTypes(),
                    fn (string $type): bool => $type !== FabaMovement::TYPE_OPENING_BALANCE
                )),
                'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
                'internalDestinations' => FabaInternalDestination::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'purposes' => FabaPurpose::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ],
            'monthlyRecap' => $monthlyRecap,
            'yearlyRecap' => [
                'year' => $yearlyRecap['year'],
                'totals' => $yearlyRecap['totals'],
                'latest_month' => $yearlyRecap['months'][max(0, $month - 1)] ?? null,
            ],
            'vendorRecap' => $this->fabaRecapService->getVendorRecap($year, $vendorId),
            'internalDestinationRecap' => $this->fabaRecapService->getInternalDestinationRecap($year, $internalDestinationId),
            'purposeRecap' => $this->fabaRecapService->getPurposeRecap($year, $purposeId),
            'stockCard' => $this->fabaRecapService->getStockCard($year, $month, $materialType),
            'anomalyReport' => $this->fabaRecapService->getAnomalyReport($year, $month),
        ]);
    }

    public function monthlyXlsx(): BinaryFileResponse
    {
        return $this->fabaOfficialReportService->downloadExcel('monthly', [
            'year' => request('year'),
            'month' => request('month'),
        ]);
    }

    public function monthlyPdf(): HttpResponse
    {
        return $this->fabaOfficialReportService->downloadPdf('monthly', [
            'year' => request('year'),
            'month' => request('month'),
        ]);
    }

    public function yearlyXlsx(): BinaryFileResponse
    {
        return $this->fabaOfficialReportService->downloadExcel('yearly', [
            'year' => request('year'),
        ]);
    }

    public function yearlyPdf(): HttpResponse
    {
        return $this->fabaOfficialReportService->downloadPdf('yearly', [
            'year' => request('year'),
        ]);
    }

    public function vendorsXlsx(): BinaryFileResponse
    {
        return $this->fabaOfficialReportService->downloadExcel('vendors', [
            'year' => request('year'),
            'vendor_id' => request('vendor_id'),
        ]);
    }

    public function vendorsPdf(): HttpResponse
    {
        return $this->fabaOfficialReportService->downloadPdf('vendors', [
            'year' => request('year'),
            'vendor_id' => request('vendor_id'),
        ]);
    }

    public function internalDestinationsXlsx(): BinaryFileResponse
    {
        return $this->fabaOfficialReportService->downloadExcel('internal-destinations', [
            'year' => request('year'),
            'internal_destination_id' => request('internal_destination_id'),
        ]);
    }

    public function internalDestinationsPdf(): HttpResponse
    {
        return $this->fabaOfficialReportService->downloadPdf('internal-destinations', [
            'year' => request('year'),
            'internal_destination_id' => request('internal_destination_id'),
        ]);
    }

    public function purposesXlsx(): BinaryFileResponse
    {
        return $this->fabaOfficialReportService->downloadExcel('purposes', [
            'year' => request('year'),
            'purpose_id' => request('purpose_id'),
        ]);
    }

    public function purposesPdf(): HttpResponse
    {
        return $this->fabaOfficialReportService->downloadPdf('purposes', [
            'year' => request('year'),
            'purpose_id' => request('purpose_id'),
        ]);
    }

    public function stockCardXlsx(): BinaryFileResponse
    {
        return $this->fabaOfficialReportService->downloadExcel('stock-card', [
            'year' => request('year'),
            'month' => request('month'),
            'material_type' => request('material_type'),
        ]);
    }

    public function stockCardPdf(): HttpResponse
    {
        return $this->fabaOfficialReportService->downloadPdf('stock-card', [
            'year' => request('year'),
            'month' => request('month'),
            'material_type' => request('material_type'),
        ]);
    }

    public function anomaliesXlsx(): BinaryFileResponse
    {
        return $this->fabaOfficialReportService->downloadExcel('anomalies', [
            'year' => request('year'),
            'month' => request('month'),
        ]);
    }

    public function anomaliesPdf(): HttpResponse
    {
        return $this->fabaOfficialReportService->downloadPdf('anomalies', [
            'year' => request('year'),
            'month' => request('month'),
        ]);
    }
}
