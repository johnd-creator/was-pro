<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardFilterRequest;
use App\Models\Organization;
use App\Services\UnifiedDashboardService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the unified application dashboard.
     */
    public function __invoke(DashboardFilterRequest $request, UnifiedDashboardService $dashboardService): Response|RedirectResponse
    {
        $user = $request->user();
        $selectedOrganization = $this->resolveSelectedOrganization($request);

        if (! $user->canAccessOrganization($selectedOrganization->id)) {
            abort(403);
        }

        $data = $dashboardService->getUnifiedData(
            $selectedOrganization,
            $request->validated('month')
        );

        return Inertia::render('Dashboard', [
            // Waste Management Data
            'stats' => $data['waste_stats'],
            'recentActivities' => $data['recent_activities'],
            'pendingApprovals' => $data['pending_approvals'],
            'tasks' => $data['tasks'],
            'wasteByCategory' => $data['waste_by_category'],
            'fabaProductionMaterialDistribution' => $data['faba_production_material_distribution'],
            'transportationByStatus' => $data['transportation_stats'],

            // FABA Data
            'fabaStats' => $data['faba_stats'],
            'fabaChart' => $data['faba_chart'],
            'fabaPendingApprovals' => $data['faba_pending'],
            'fabaWarnings' => $data['faba_warnings'],
            'latestFabaPeriod' => $data['faba_latest'],
            'topVendors' => collect($data['top_vendors'])
                ->sortByDesc('total_quantity')
                ->take(5)
                ->values(),

            // New Chart Data
            'wasteChart' => $data['waste_chart'],

            // Notifications & Header
            'organizationName' => $data['header']['organization_name'],
            'notificationCount' => $data['notification_summary']['total_count'],
            'headerRiskLabel' => $data['header']['risk_label'],
            'headerRiskTone' => $data['header']['risk_tone'],
            'notificationSummary' => $data['notification_summary'],
            'header' => $data['header'],
            'taskContext' => $data['header']['user']['role'] === 'operator' ? 'operator' : 'approver',
            'filters' => $data['filters'],
            'availableMonths' => $data['available_months'],
            'availableOrganizations' => $user->isSuperAdmin()
                ? Organization::query()
                    ->active()
                    ->orderBy('name')
                    ->get(['id', 'name', 'code'])
                    ->map(fn (Organization $organization): array => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'code' => $organization->code,
                    ])
                    ->values()
                    ->all()
                : [],

            // NEW: Tab system props
            'wasteTasks' => $data['waste_tasks'],
            'fabaTasks' => $data['faba_tasks'],
            'haulingAttentionTasks' => $data['hauling_attention_tasks'],
            'wastePendingCount' => $data['waste_pending_count'],
            'fabaPendingCount' => $data['faba_pending_count'],
            'haulingAttentionCount' => $data['hauling_attention_count'],
        ]);
    }

    private function resolveSelectedOrganization(DashboardFilterRequest $request): Organization
    {
        $user = $request->user();
        $requestedOrganizationId = $request->validated('organization_id');

        if (! $user->isSuperAdmin()) {
            return $user->organization ?? abort(403);
        }

        if ($requestedOrganizationId) {
            return Organization::query()
                ->active()
                ->whereKey($requestedOrganizationId)
                ->first() ?? abort(403);
        }

        if ($user->organization && $user->organization->is_active) {
            return $user->organization;
        }

        return Organization::query()
            ->active()
            ->orderBy('name')
            ->first() ?? abort(403);
    }
}
