<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\StoreFabaOpeningBalanceRequest;
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
        protected FabaAuditService $fabaAuditService
    ) {}

    public function monthly(): Response
    {
        $year = (int) request('year', now()->year);
        $month = (int) request('month', now()->month);

        return Inertia::render('waste-management/faba/recaps/Monthly', [
            'detail' => $this->fabaRecapService->getMonthlyRecapDetail($year, $month),
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
        return Inertia::render('waste-management/faba/recaps/Balance', [
            'currentBalance' => $this->fabaRecapService->getCurrentBalance(),
            'yearlyRecap' => $this->fabaRecapService->getYearlyRecap((int) now()->year),
            'openingBalanceDefaults' => [
                'year' => (int) now()->year,
                'month' => (int) now()->month,
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
}
