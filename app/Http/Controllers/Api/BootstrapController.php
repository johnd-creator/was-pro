<?php

namespace App\Http\Controllers\Api;

use App\Models\FabaInternalDestination;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaPurpose;
use App\Models\Vendor;
use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use App\Models\WasteType;
use App\Services\FabaRecapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController extends ApiController
{
    public function __construct(protected FabaRecapService $fabaRecapService) {}

    public function me(Request $request): JsonResponse
    {
        $controller = app(AuthController::class);

        return $controller->me($request);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $year = (int) now()->year;
        $fabaYearlyRecap = $this->fabaRecapService->getYearlyRecap($year);

        $data = [
            'organization' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'code' => $user->organization->code,
            ] : null,
            'waste' => [
                'total_records' => WasteRecord::query()->count(),
                'draft_records' => WasteRecord::query()->draft()->count(),
                'pending_records' => WasteRecord::query()->pendingApproval()->count(),
                'approved_records' => WasteRecord::query()->approved()->count(),
                'total_transportations' => WasteTransportation::query()->count(),
                'pending_transportations' => WasteTransportation::query()->pending()->count(),
                'in_transit_transportations' => WasteTransportation::query()->inTransit()->count(),
            ],
            'faba' => [
                'current_balance' => $this->fabaRecapService->getCurrentBalance(),
                'total_production' => $fabaYearlyRecap['totals']['total_production'],
                'total_utilization' => $fabaYearlyRecap['totals']['total_utilization'],
                'pending_approvals' => FabaMonthlyApproval::query()
                    ->where('status', FabaMonthlyApproval::STATUS_SUBMITTED)
                    ->count(),
                'negative_periods' => collect($fabaYearlyRecap['months'])
                    ->filter(fn (array $month): bool => (bool) $month['warning_negative_balance'])
                    ->count(),
            ],
        ];

        return $this->success($data, meta: [
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function lookups(): JsonResponse
    {
        return $this->success([
            'waste_categories' => WasteCategory::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'waste_characteristics' => WasteCharacteristic::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_hazardous']),
            'waste_types' => WasteType::query()
                ->with(['category:id,name,code', 'characteristic:id,name,code'])
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'category_id', 'characteristic_id', 'storage_period_days']),
            'vendors' => Vendor::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'license_expiry_date']),
            'faba_internal_destinations' => FabaInternalDestination::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'faba_purposes' => FabaPurpose::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ], meta: [
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
