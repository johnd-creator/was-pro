<?php

namespace App\Http\Controllers;

use App\Services\UnifiedDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the unified application dashboard.
     */
    public function __invoke(Request $request, UnifiedDashboardService $dashboardService): Response
    {
        $data = $dashboardService->getUnifiedData();

        return Inertia::render('Dashboard', [
            // Waste Management Data
            'stats' => $data['waste_stats'],
            'recentActivities' => $data['recent_activities'],
            'pendingApprovals' => $data['pending_approvals'],
            'wasteByCategory' => $data['waste_by_category'],
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
            'notificationSummary' => $data['notification_summary'],
            'header' => $data['header'],
        ]);
    }
}
