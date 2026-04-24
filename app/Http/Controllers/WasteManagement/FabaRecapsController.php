<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\StoreFabaOpeningBalanceRequest;
use App\Http\Requests\WasteManagement\StoreFabaTpsCapacityRequest;
use App\Models\FabaAuditLog;
use App\Models\Vendor;
use App\Services\FabaAuditService;
use App\Services\FabaRecapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class FabaRecapsController extends Controller
{
    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService,
    ) {}

    public function monthly(): Response
    {
        $resolvedPeriod = $this->fabaRecapService->resolveRequestedOrLatestPeriod(
            request()->filled('year') ? (int) request('year') : null,
            request()->filled('month') ? (int) request('month') : null,
        );
        $year = $resolvedPeriod['year'];
        $month = $resolvedPeriod['month'];

        return Inertia::render('waste-management/faba/recaps/Monthly', [
            'detail' => $this->fabaRecapService->getMonthlyRecapDetail($year, $month),
            'availablePeriods' => $this->fabaRecapService->getAvailablePeriodOptions(),
            'resolvedFromLatestPeriod' => $resolvedPeriod['resolved_from_latest'],
            'filters' => compact('year', 'month'),
        ]);
    }

    public function yearly(): Response
    {
        $year = (int) request('year', now()->year);

        return Inertia::render('waste-management/faba/recaps/Yearly', [
            'recap' => $this->fabaRecapService->getYearlyRecap($year),
            'filters' => compact('year'),
        ]);
    }

    public function vendors(): Response
    {
        $year = (int) request('year', now()->year);
        $vendorId = request('vendor_id');

        return Inertia::render('waste-management/faba/recaps/Vendors', [
            'recap' => $this->fabaRecapService->getVendorRecap($year, $vendorId),
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'year' => $year,
                'vendor_id' => $vendorId,
            ],
        ]);
    }

    public function balance(): Response
    {
        $resolvedPeriod = $this->fabaRecapService->resolveRequestedOrLatestPeriod();

        return Inertia::render('waste-management/faba/recaps/Balance', [
            'currentBalance' => $this->fabaRecapService->getCurrentBalance(),
            'yearlyRecap' => $this->fabaRecapService->getYearlyRecap($resolvedPeriod['year']),
            'canManageOpeningBalance' => Auth::user()?->hasPermission('faba_opening_balance.manage') ?? false,
            'tpsCapacitySummary' => $this->fabaRecapService->getTpsCapacitySummary($resolvedPeriod['year'], $resolvedPeriod['month']),
            'openingBalanceDefaults' => [
                'year' => $resolvedPeriod['year'],
                'month' => $resolvedPeriod['month'],
            ],
        ]);
    }

    public function stockCard(): Response
    {
        $year = (int) request('year', now()->year);
        $month = request()->filled('month') ? (int) request('month') : null;
        $materialType = request('material_type');

        return Inertia::render('waste-management/faba/recaps/StockCard', [
            'stockCard' => $this->fabaRecapService->getStockCard($year, $month, $materialType),
            'filters' => [
                'year' => $year,
                'month' => $month,
                'material_type' => $materialType,
            ],
            'options' => [
                'materials' => \App\Models\FabaMovement::materialOptions(),
                'months' => collect(range(1, 12))->map(fn (int $item): array => [
                    'value' => $item,
                    'label' => $this->fabaRecapService->formatMonthLabel($item),
                ])->all(),
            ],
        ]);
    }

    public function storeOpeningBalance(StoreFabaOpeningBalanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $balance = $this->fabaRecapService->setOpeningBalance(
            (int) $validated['year'],
            (int) $validated['month'],
            (string) $validated['material_type'],
            (float) $validated['quantity'],
            $validated['note'] ?? null,
            Auth::id(),
        );

        $this->fabaAuditService->log(
            Auth::id(),
            'set_opening_balance',
            FabaAuditLog::MODULE_BALANCE,
            get_class($balance),
            $balance->id,
            (int) $validated['year'],
            (int) $validated['month'],
            'Opening balance disimpan.',
            $validated
        );

        return back()->with('success', 'Opening balance berhasil disimpan.');
    }

    public function storeTpsCapacity(StoreFabaTpsCapacityRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $capacity = $this->fabaRecapService->setTpsCapacity(
            (string) $validated['material_type'],
            (float) $validated['capacity'],
            (float) $validated['warning_threshold'],
            (float) $validated['critical_threshold'],
            Auth::id(),
        );

        $this->fabaAuditService->log(
            Auth::id(),
            'set_tps_capacity',
            FabaAuditLog::MODULE_BALANCE,
            get_class($capacity),
            $capacity->id,
            (int) now()->year,
            (int) now()->month,
            'Kapasitas TPS FABA diperbarui.',
            [
                'material_type' => $capacity->material_type,
                'capacity' => (float) $capacity->capacity,
                'warning_threshold' => (float) $capacity->warning_threshold,
                'critical_threshold' => (float) $capacity->critical_threshold,
            ]
        );

        return back()->with('success', 'Kapasitas TPS berhasil disimpan.');
    }
}
