<?php

namespace App\Http\Controllers\Api;

use App\Models\FabaInternalDestination;
use App\Models\FabaMovement;
use App\Models\FabaPurpose;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FabaOptionsController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessAnyFabaModule($user)) {
            return $this->error('Anda tidak memiliki izin untuk mengakses modul FABA.', 'FORBIDDEN', status: 403);
        }

        return $this->success([
            'materials' => FabaMovement::materialOptions(),
            'production_movement_types' => FabaMovement::productionTypeOptions(),
            'utilization_movement_types' => FabaMovement::utilizationTypeOptions(),
            'adjustment_movement_types' => [
                FabaMovement::TYPE_ADJUSTMENT_IN,
                FabaMovement::TYPE_ADJUSTMENT_OUT,
            ],
            'vendors' => Vendor::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'internal_destinations' => FabaInternalDestination::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'purposes' => FabaPurpose::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ], meta: [
            'server_time' => now()->toIso8601String(),
        ]);
    }

    protected function canAccessAnyFabaModule($user): bool
    {
        return $user->hasPermission('faba_production.view')
            || $user->hasPermission('faba_production.create')
            || $user->hasPermission('faba_utilization.view')
            || $user->hasPermission('faba_utilization.create')
            || $user->hasPermission('faba_adjustments.view')
            || $user->hasPermission('faba_adjustments.create');
    }
}
