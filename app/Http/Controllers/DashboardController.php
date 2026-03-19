<?php

namespace App\Http\Controllers;

use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard.
     */
    public function __invoke(): Response
    {
        $user = Auth::user();

        // Get basic statistics for the dashboard
        $stats = [
            'total_waste_records' => WasteRecord::count(),
            'approved_records' => WasteRecord::approved()->count(),
            'pending_records' => WasteRecord::pendingApproval()->count(),
            'total_transportations' => WasteTransportation::count(),
            'in_transit_transportations' => WasteTransportation::inTransit()->count(),
            'expired_waste' => WasteRecord::approved()->expired()->count(),
            'expiring_soon_waste' => WasteRecord::approved()->expiringSoon(7)->count(),
        ];

        // Get recent activities
        $recentActivities = $this->getRecentActivities();

        // Get pending approvals (if user has permission)
        $pendingApprovals = [];
        if ($user->can('waste_records.approve') || $user->can('waste_records.view_all')) {
            $pendingApprovals = WasteRecord::pendingApproval()
                ->with(['wasteType', 'wasteType.category', 'submittedByUser'])
                ->orderBy('submitted_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'record_number' => $record->record_number,
                        'waste_type' => $record->wasteType->name,
                        'category' => $record->wasteType->category->name ?? 'N/A',
                        'quantity' => $record->quantity,
                        'unit' => $record->unit,
                        'submitted_by' => $record->submittedByUser?->name ?? 'Unknown',
                        'submitted_at' => $record->submitted_at->toIso8601String(),
                    ];
                });
        }

        // Get waste by category statistics
        $wasteByCategory = WasteRecord::approved()
            ->join('waste_types', 'waste_records.waste_type_id', '=', 'waste_types.id')
            ->join('waste_categories', 'waste_types.category_id', '=', 'waste_categories.id')
            ->selectRaw('waste_categories.name as category, COUNT(*) as count')
            ->groupBy('waste_categories.name')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) use ($stats) {
                return [
                    'category' => $item->category,
                    'count' => $item->count,
                    'percentage' => $stats['total_waste_records'] > 0
                        ? ($item->count / $stats['total_waste_records']) * 100
                        : 0,
                ];
            });

        // Get transportation status distribution
        $transportationByStatus = [
            [
                'status' => 'pending',
                'count' => WasteTransportation::pending()->count(),
                'color' => 'bg-yellow-500',
                'percentage' => $stats['total_transportations'] > 0
                    ? (WasteTransportation::pending()->count() / $stats['total_transportations']) * 100
                    : 0,
            ],
            [
                'status' => 'in_transit',
                'count' => WasteTransportation::inTransit()->count(),
                'color' => 'bg-blue-500',
                'percentage' => $stats['total_transportations'] > 0
                    ? (WasteTransportation::inTransit()->count() / $stats['total_transportations']) * 100
                    : 0,
            ],
            [
                'status' => 'delivered',
                'count' => WasteTransportation::delivered()->count(),
                'color' => 'bg-green-500',
                'percentage' => $stats['total_transportations'] > 0
                    ? (WasteTransportation::delivered()->count() / $stats['total_transportations']) * 100
                    : 0,
            ],
            [
                'status' => 'cancelled',
                'count' => WasteTransportation::cancelled()->count(),
                'color' => 'bg-red-500',
                'percentage' => $stats['total_transportations'] > 0
                    ? (WasteTransportation::cancelled()->count() / $stats['total_transportations']) * 100
                    : 0,
            ],
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentActivities' => $recentActivities,
            'pendingApprovals' => $pendingApprovals,
            'wasteByCategory' => $wasteByCategory,
            'transportationByStatus' => $transportationByStatus,
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
                'id' => $record->id,
                'type' => 'waste_record',
                'action' => 'created',
                'description' => "Waste record {$record->record_number} created: {$record->wasteType->name} ({$record->quantity} {$record->unit})",
                'user_name' => $record->createdBy?->name ?? 'System',
                'created_at' => $record->created_at->toIso8601String(),
                'link' => "/waste-management/records/{$record->id}",
            ];
        }

        // Recent transportations
        $recentTransportations = WasteTransportation::with(['wasteRecord.wasteType', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentTransportations as $transportation) {
            $actions = [
                'pending' => 'scheduled',
                'in_transit' => 'dispatched',
                'delivered' => 'completed',
                'cancelled' => 'cancelled',
            ];

            $activities[] = [
                'id' => $transportation->id,
                'type' => 'transportation',
                'action' => $transportation->status === 'pending' ? 'created' : $transportation->status,
                'description' => "Transportation {$transportation->transportation_number}: {$actions[$transportation->status]}",
                'user_name' => $transportation->createdBy?->name ?? 'System',
                'created_at' => $transportation->created_at->toIso8601String(),
                'link' => "/waste-management/transportations/{$transportation->id}",
            ];
        }

        // Sort by created_at and limit to 10
        usort($activities, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($activities, 0, 10);
    }
}
