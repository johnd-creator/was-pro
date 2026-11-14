<?php

namespace App\Services;

use App\Http\Middleware\UseOrganizationContext;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteHaulingPlan;
use App\Models\WasteRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OperationalAlerts
{
    public function snapshot(?string $month = null): CarbonImmutable
    {
        $date = $month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? CarbonImmutable::createFromFormat('!Y-m', $month) : CarbonImmutable::now()->startOfMonth();

        return $date->isCurrentMonth() ? CarbonImmutable::now()->startOfDay() : $date->endOfMonth()->startOfDay();
    }

    public function records(User $user): Builder
    {
        $query = WasteRecord::query();
        if ($user->hasPermission('waste_records.view_all')) {
            return $query;
        }

        return $user->hasPermission('waste_records.view_own') ? $query->byUser($user->id) : $query->whereRaw('false');
    }

    public function storageAlert(WasteRecord $record, CarbonImmutable $snapshot): ?string
    {
        if ($record->status !== 'approved' || $record->getRemainingQuantity() <= 0 || ! $record->expiry_date) {
            return null;
        }
        $expiry = $record->expiry_date->toDateString();
        if ($expiry < $snapshot->toDateString()) {
            return 'expired';
        }

        return $expiry <= $snapshot->addDays(7)->toDateString() ? 'expiring_soon' : null;
    }

    public function matchesAttention(WasteRecord $record, ?string $attention, CarbonImmutable $snapshot): bool
    {
        $alert = $this->storageAlert($record, $snapshot);

        return match ($attention) {
            'expired', 'expiring_soon' => $alert === $attention,
            'critical' => $alert !== null,
            'backlog' => $record->status === 'approved' && $record->getRemainingQuantity() > 0,
            'completed' => $record->status === 'approved' && $record->getRemainingQuantity() <= 0,
            'fresh' => $record->status === 'approved' && $record->getRemainingQuantity() > 0 && $record->expiry_date !== null && $alert === null,
            'pending' => $record->status === 'pending_review' && $record->date->format('Y-m') === $snapshot->format('Y-m'),
            default => true,
        };
    }

    public function forRequest(Request $request): array
    {
        $organization = $request->attributes->get(UseOrganizationContext::ATTRIBUTE);
        $user = $request->user();
        $month = $request->attributes->get('notification_month', $request->query('month'));
        if (! is_string($month) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $year = $request->route('year') ?? $request->query('year');
            $number = $request->route('month') ?? $request->query('month');
            $month = filter_var($year, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1900, 'max_range' => 9999]]) !== false
                && filter_var($number, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) !== false
                    ? sprintf('%04d-%02d', $year, $number) : null;
        }
        $key = 'operational_alerts.'.hash('sha256', json_encode([
            $month ?? 'current', $organization?->id, $organization?->schema_name,
            $user?->id, $user?->organization_id, $user?->role_id, $user?->is_super_admin,
        ]));
        if ($request->attributes->has($key)) {
            return $request->attributes->get($key);
        }
        $result = $this->emptySummary($month);
        if ($user && $organization instanceof Organization && $user->canAccessOrganization($organization->id)) {
            $tenants = app(TenantService::class);
            if ($tenants->schemaExists($organization->schema_name)) {
                $result = $tenants->withRestoredSchema(function () use ($tenants, $organization, $user, $month): array {
                    $tenants->switchToSchema($organization->schema_name);

                    return $this->summary($user, $organization, $month);
                });
            }
        }
        $request->attributes->set($key, $result);

        return $result;
    }

    private function emptySummary(?string $month): array
    {
        $snapshot = $this->snapshot($month);

        return [
            'summary' => ['expired_waste_count' => 0, 'expiring_soon_waste_count' => 0, 'pending_waste_approvals_count' => 0, 'pending_faba_approvals_count' => 0, 'pending_hauling_plans_count' => 0, 'pending_hauling_approvals_count' => 0, 'faba_warnings_count' => 0, 'total_count' => 0],
            'context' => ['month' => $snapshot->format('Y-m'), 'as_of' => $snapshot->toDateString(), 'organization_id' => null],
            'alerts' => [],
        ];
    }

    public function summary(User $user, Organization $organization, ?string $month): array
    {
        abort_unless($user->canAccessOrganization($organization->id), 403);
        $result = $this->emptySummary($month);
        $snapshot = $this->snapshot($month);
        $month = $snapshot->format('Y-m');
        $result['context']['organization_id'] = $organization->id;
        $canReviewHaulings = $user->hasPermission('waste_hauling.approve') || $user->hasPermission('waste_hauling.reject');
        $canViewFabaApprovals = $user->hasPermission('faba_approvals.view');
        $canViewFabaRecaps = $user->hasPermission('faba_recaps.view');
        $wasteReady = $this->hasTables($organization, ['waste_records', 'waste_haulings']);
        $fabaReady = ($canViewFabaApprovals || $canViewFabaRecaps)
            && $this->hasTables($organization, ['faba_movements', 'faba_monthly_approvals', 'faba_opening_balances']);
        if ($wasteReady) {
            $records = $this->records($user);
            $counts = $this->storageCounts($records, $snapshot);
            foreach (['expired', 'expiring_soon'] as $kind) {
                $count = $counts[$kind];
                $result['summary'][$kind.'_waste_count'] = $count;
                if ($count) {
                    $result['alerts'][] = ['id' => $kind, 'title' => $kind === 'expired' ? 'Melewati batas simpan' : 'Mendekati batas simpan', 'description' => 'Catatan disetujui yang masih memiliki sisa stok pada '.$snapshot->toDateString().'.', 'count' => $count, 'tone' => $kind === 'expired' ? 'red' : 'orange', 'source_url' => route('waste-management.records.index', ['organization_id' => $organization->id, 'month' => $month, 'attention' => $kind])];
                }
            }
            $pending = (clone $records)->pendingApproval()->whereBetween('date', [$snapshot->startOfMonth()->toDateString(), $snapshot->toDateString()])->count();
            $result['summary']['pending_waste_approvals_count'] = $pending;
            if ($pending) {
                $result['alerts'][] = ['id' => 'pending_waste', 'title' => 'Catatan menunggu ditinjau', 'description' => 'Catatan sudah diajukan dan menunggu keputusan.', 'count' => $pending, 'tone' => 'orange', 'source_url' => route('waste-management.records.index', ['organization_id' => $organization->id, 'month' => $month, 'attention' => 'pending'])];
            }
        }
        // Actual approvals are actionable across periods, independently of the plan/inbox feature.
        if ($wasteReady && $canReviewHaulings) {
            $haulings = WasteHauling::query()->pendingApproval()->with('wasteRecord')->orderBy('submitted_at')->get()
                ->filter(fn (WasteHauling $hauling): bool => app(ApprovalSeparation::class)->canApprove($user, $hauling));
            $result['summary']['pending_hauling_approvals_count'] = $haulings->count();
            foreach ($haulings as $hauling) {
                $result['alerts'][] = [
                    'id' => 'hauling_actual_'.$hauling->id, 'title' => 'Realisasi angkut menunggu ditinjau',
                    'description' => $hauling->hauling_number.' - '.($hauling->wasteRecord?->record_number ?? 'Catatan limbah'),
                    'count' => 1, 'tone' => 'orange',
                    'submitted_at' => $hauling->submitted_at?->toIso8601String(),
                    'source_url' => route('waste-management.haulings.show', ['wasteHauling' => $hauling->id, 'organization_id' => $organization->id]),
                ];
            }
        }
        // Pending plans remain actionable across months, including plans submitted before this release.
        if ($wasteReady && $canReviewHaulings && config('hauling-plans.enabled', false)
            && $this->hasTables($organization, ['waste_hauling_plans'])
            && app(HaulingPlans::class)->enabled()) {
            $plans = WasteHaulingPlan::query()->where('status', 'pending_approval')
                ->with('wasteRecord')->orderBy('submitted_at')->get()
                ->filter(fn (WasteHaulingPlan $plan): bool => app(ApprovalSeparation::class)->canApprove($user, $plan));
            $result['summary']['pending_hauling_plans_count'] = $plans->count();
            foreach ($plans as $plan) {
                $result['alerts'][] = [
                    'id' => 'hauling_plan_'.$plan->id, 'title' => 'Rencana angkut menunggu ditinjau',
                    'description' => ($plan->wasteRecord?->record_number ?? 'Catatan limbah').' - Rencana '.$plan->planned_date?->toDateString(),
                    'count' => 1, 'tone' => 'orange',
                    'source_url' => route('waste-management.hauling-plans.show', ['wasteHaulingPlan' => $plan->id, 'organization_id' => $organization->id]),
                ];
            }
        }
        if ($fabaReady && $canViewFabaApprovals) {
            $pendingFaba = FabaMonthlyApproval::query()->where('year', $snapshot->year)->where('month', $snapshot->month)->where('status', FabaMonthlyApproval::STATUS_SUBMITTED)->get();
            $result['summary']['pending_faba_approvals_count'] = $pendingFaba->count();
            foreach ($pendingFaba as $approval) {
                $result['alerts'][] = ['id' => 'faba_'.$approval->id, 'title' => 'Closing FABA menunggu ditinjau', 'description' => $month, 'count' => 1, 'tone' => 'orange', 'source_url' => route('waste-management.faba.approvals.review', [$approval->year, $approval->month, 'organization_id' => $organization->id])];
            }
        }
        if ($fabaReady && $canViewFabaRecaps && (FabaMovement::query()->forPeriod($snapshot->year, $snapshot->month)->exists() || FabaMonthlyApproval::query()->where('year', $snapshot->year)->where('month', $snapshot->month)->exists())) {
            $warnings = collect(app(FabaRecapService::class)->getMonthlyRecap($snapshot->year, $snapshot->month)['warnings'])->reject(fn (array $warning): bool => $warning['code'] === 'missing_opening_balance');
            $result['summary']['faba_warnings_count'] = $warnings->count();
            foreach ($warnings as $index => $warning) {
                $result['alerts'][] = ['id' => 'warning_'.$index, 'title' => 'Perhatian FABA', 'description' => $warning['message'], 'count' => 1, 'tone' => 'red', 'source_url' => route('waste-management.faba.recaps.monthly', ['year' => $snapshot->year, 'month' => $snapshot->month, 'organization_id' => $organization->id])];
            }
        }
        if ($fabaReady && $canViewFabaRecaps) {
            $capacity = app(FabaRecapService::class)->getTpsCapacitySummary($snapshot->year, $snapshot->month);
            $items = collect([$capacity['total'], ...$capacity['materials']]);
            $critical = $items->contains(fn (array $item): bool => $item['available'] && $item['status'] === 'critical');
            $warning = $items->contains(fn (array $item): bool => ! $item['available'] || $item['status'] === 'warning');
            if ($critical || $warning) {
                $result['summary']['faba_warnings_count']++;
                $result['alerts'][] = [
                    'id' => 'faba_capacity', 'title' => 'Kapasitas TPS FABA perlu perhatian',
                    'description' => $critical ? 'Kapasitas TPS mencapai ambang kritis.' : 'Kapasitas TPS mendekati batas atau belum dikonfigurasi.',
                    'count' => 1, 'tone' => $critical ? 'red' : 'orange',
                    'source_url' => route('waste-management.faba.recaps.monthly', ['year' => $snapshot->year, 'month' => $snapshot->month, 'organization_id' => $organization->id]),
                ];
            }
        }
        $result['summary']['total_count'] = array_sum($result['summary']);

        return $result;
    }

    /**
     * Count current storage risks without hydrating historical records or haulings.
     * Only approved, non-deleted haulings through the snapshot reduce stock.
     *
     * @return array{expired: int, expiring_soon: int}
     */
    private function storageCounts(Builder $records, CarbonImmutable $snapshot): array
    {
        $haulings = WasteHauling::query()->approved()
            ->where('hauling_date', '<=', $snapshot->toDateString())
            ->select('waste_record_id')
            ->selectRaw('SUM(quantity) AS hauled_quantity')
            ->groupBy('waste_record_id');

        $counts = (clone $records)->approved()
            ->where('date', '<=', $snapshot->toDateString())
            ->where('expiry_date', '<=', $snapshot->addDays(7)->toDateString())
            ->leftJoinSub($haulings, 'alert_haulings', 'waste_records.id', '=', 'alert_haulings.waste_record_id')
            ->whereRaw('waste_records.quantity > COALESCE(alert_haulings.hauled_quantity, 0)')
            ->selectRaw('COUNT(CASE WHEN expiry_date < ? THEN 1 END) AS expired, COUNT(CASE WHEN expiry_date >= ? THEN 1 END) AS expiring_soon', [
                $snapshot->toDateString(), $snapshot->toDateString(),
            ])
            ->toBase()->first();

        return ['expired' => (int) $counts->expired, 'expiring_soon' => (int) $counts->expiring_soon];
    }

    private function hasTables(Organization $organization, array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($organization->schema_name.'.'.$table)) {
                return false;
            }
        }

        return true;
    }
}
