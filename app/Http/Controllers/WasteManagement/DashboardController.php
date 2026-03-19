<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\WasteCategory;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use App\Models\WasteType;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the waste management dashboard.
     */
    public function index(): Response
    {
        $user = Auth::user();

        // Get date range for statistics (last 30 days)
        $startDate = now()->subDays(30)->startOfDay();
        $endDate = now()->endOfDay();

        // Basic Statistics
        $stats = [
            // Waste Records Statistics
            'total_waste_records' => WasteRecord::count(),
            'draft_records' => WasteRecord::draft()->count(),
            'pending_records' => WasteRecord::pendingApproval()->count(),
            'approved_records' => WasteRecord::approved()->count(),
            'rejected_records' => WasteRecord::where('status', 'rejected')->count(),

            // Transportation Statistics
            'total_transportations' => WasteTransportation::count(),
            'pending_transportations' => WasteTransportation::pending()->count(),
            'in_transit_transportations' => WasteTransportation::inTransit()->count(),
            'delivered_transportations' => WasteTransportation::delivered()->count(),

            // Expiry Statistics
            'expired_waste' => WasteRecord::approved()->expired()->count(),
            'expiring_soon_waste' => WasteRecord::approved()->expiringSoon(7)->count(),

            // Master Data Counts
            'total_waste_types' => WasteType::active()->count(),
            'total_categories' => WasteCategory::active()->count(),
            'total_vendors' => Vendor::active()->count(),
        ];

        // Waste by Category (for pie chart)
        $wasteByCategory = WasteRecord::approved()
            ->join('waste_types', 'waste_records.waste_type_id', '=', 'waste_types.id')
            ->join('waste_categories', 'waste_types.category_id', '=', 'waste_categories.id')
            ->selectRaw('waste_categories.name, SUM(waste_records.quantity) as total_quantity, waste_records.unit')
            ->groupBy('waste_categories.name', 'waste_records.unit')
            ->orderByDesc('total_quantity')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->name,
                    'quantity' => (float) $item->total_quantity,
                    'unit' => $item->unit,
                ];
            });

        // Waste Trends (last 30 days, for line chart)
        $wasteTrends = WasteRecord::approved()
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('DATE(date) as date, SUM(quantity) as total_quantity, unit')
            ->groupBy('date', 'unit')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($items) {
                return [
                    'date' => $items->first()->date->format('Y-m-d'),
                    'quantity' => (float) $items->sum('total_quantity'),
                    'unit' => $items->first()->unit,
                ];
            })->values();

        // Transportation Status Distribution
        $transportationByStatus = [
            'pending' => WasteTransportation::pending()->count(),
            'in_transit' => WasteTransportation::inTransit()->count(),
            'delivered' => WasteTransportation::delivered()->count(),
            'cancelled' => WasteTransportation::cancelled()->count(),
        ];

        // Recent Activities (last 10)
        $recentActivities = $this->getRecentActivities();

        // Expiring Soon & Expired Waste (Alerts)
        $expiringSoon = WasteRecord::approved()
            ->expiringSoon(7)
            ->with(['wasteType', 'wasteType.category'])
            ->orderBy('expiry_date')
            ->limit(5)
            ->get();

        $expiredWaste = WasteRecord::approved()
            ->expired()
            ->with(['wasteType', 'wasteType.category'])
            ->orderByDesc('expiry_date')
            ->limit(5)
            ->get();

        // Pending Approvals (for supervisors/admins)
        $pendingApprovals = [];
        if ($user->can('waste_records.approve') || $user->can('waste_records.view_all')) {
            $pendingApprovals = WasteRecord::pendingApproval()
                ->with(['wasteType', 'wasteType.category', 'submittedByUser'])
                ->orderBy('submitted_at', 'desc')
                ->limit(5)
                ->get();
        }

        return Inertia::render('waste-management/Dashboard', [
            'stats' => $stats,
            'wasteByCategory' => $wasteByCategory,
            'wasteTrends' => $wasteTrends,
            'transportationByStatus' => $transportationByStatus,
            'recentActivities' => $recentActivities,
            'expiringSoon' => $expiringSoon,
            'expiredWaste' => $expiredWaste,
            'pendingApprovals' => $pendingApprovals,
            'dateRange' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Get recent activities across the system.
     */
    private function getRecentActivities(): array
    {
        $activities = [];

        // Recent waste records
        $recentRecords = WasteRecord::with(['wasteType', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentRecords as $record) {
            $activities[] = [
                'type' => 'waste_record',
                'icon' => '📝',
                'title' => 'Waste record created',
                'description' => "{$record->wasteType->name} ({$record->quantity} {$record->unit})",
                'user' => $record->createdBy?->name ?? 'System',
                'created_at' => $record->created_at->diffForHumans(),
            ];
        }

        // Recent transportations
        $recentTransportations = WasteTransportation::with(['vendor', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentTransportations as $transportation) {
            $statusLabels = [
                'pending' => 'scheduled',
                'in_transit' => 'dispatched',
                'delivered' => 'completed',
                'cancelled' => 'cancelled',
            ];

            $activities[] = [
                'type' => 'transportation',
                'icon' => '🚚',
                'title' => "Transportation {$statusLabels[$transportation->status]}",
                'description' => "{$transportation->quantity} {$transportation->unit} via {$transportation->vendor->name}",
                'user' => $transportation->createdBy?->name ?? 'System',
                'created_at' => $transportation->created_at->diffForHumans(),
            ];
        }

        // Sort by created_at and limit to 10
        usort($activities, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($activities, 0, 10);
    }
}
