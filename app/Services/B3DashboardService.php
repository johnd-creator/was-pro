<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class B3DashboardService
{
    public function __construct(private WasteBalanceService $balance) {}

    public function dashboard(User $user, string $start, string $end): array
    {
        $base = WasteRecord::query()
            ->when(! $user->hasPermission('waste_records.view_all'), fn (Builder $query) => $query->byUser($user->id));
        $period = (clone $base)->whereBetween('date', [$start, $end]);
        $report = $this->balance->report($user, $start, $end);
        $remaining = (clone $base)->approved()->whereDate('date', '<=', $end)
            ->with(['wasteType:id,name,code', 'createdBy:id,name', 'haulings' => fn ($query) => $query->approved()->whereDate('hauling_date', '<=', $end)])
            ->get()->map(function (WasteRecord $record) use ($end): ?array {
                $hauled = $record->haulings->where('unit', $record->unit)->sum(fn ($hauling): int => (int) round((float) $hauling->quantity * 100));
                $quantity = (int) round((float) $record->quantity * 100) - $hauled;
                if ($quantity <= 0) {
                    return null;
                }

                $days = $record->expiry_date ? (int) CarbonImmutable::parse($end)->diffInDays($record->expiry_date, false) : null;

                return [
                    'id' => $record->id, 'record_number' => $record->record_number,
                    'waste_type' => $record->wasteType?->name ?? 'Jenis tidak tersedia',
                    'quantity' => $quantity / 100, 'unit' => $record->unit,
                    'expiry_date' => $record->expiry_date?->toDateString(), 'days_remaining' => $days,
                    'urgency' => $days === null ? 'unknown' : ($days < 0 ? 'expired' : ($days <= 7 ? 'soon' : 'normal')),
                    'input_by' => $record->createdBy?->name ?? 'Pengguna tidak tersedia',
                ];
            })->filter()->values();
        $attention = $remaining->whereIn('urgency', ['expired', 'soon', 'unknown'])
            ->sortBy(fn (array $row): int => $row['days_remaining'] ?? PHP_INT_MAX)->values();

        return [
            'report' => $report,
            'stats' => [
                'period_records' => (clone $period)->count(),
                'pending_records' => (clone $period)->pendingApproval()->count(),
                'remaining_records' => $remaining->count(),
                'expired_records' => $remaining->where('urgency', 'expired')->count(),
                'soon_records' => $remaining->where('urgency', 'soon')->count(),
                'unknown_expiry_records' => $remaining->where('urgency', 'unknown')->count(),
                'attention_count' => $attention->count(),
                'anomaly_count' => collect($report['rows'])->sum('anomaly_count'),
            ],
            'attention' => $attention->take(10)->all(),
            'recentRecords' => (clone $period)->with(['wasteType:id,name', 'createdBy:id,name'])
                ->orderByDesc('date')->orderByDesc('created_at')->limit(8)->get()
                ->map(fn (WasteRecord $record): array => [
                    'id' => $record->id, 'record_number' => $record->record_number,
                    'date' => $record->date->toDateString(), 'waste_type' => $record->wasteType?->name ?? 'Jenis tidak tersedia',
                    'quantity' => (float) $record->quantity, 'unit' => $record->unit, 'status' => $record->status,
                    'input_by' => $record->createdBy?->name ?? 'Pengguna tidak tersedia',
                ])->all(),
        ];
    }
}
