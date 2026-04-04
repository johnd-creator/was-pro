<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\StoreFabaOpeningBalanceRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaMovement;
use App\Models\Vendor;
use App\Services\FabaAuditService;
use App\Services\FabaRecapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FabaRecapController extends ApiController
{
    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canViewAnyFabaDashboard($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat dashboard FABA.', 'FORBIDDEN', status: 403);
        }

        $resolvedPeriod = $this->fabaRecapService->resolveRequestedOrLatestPeriod(
            $request->integer('year') ?: null,
            $request->integer('month') ?: null,
        );
        $recap = $this->fabaRecapService->getMonthlyRecap($resolvedPeriod['year'], $resolvedPeriod['month']);
        $approval = $this->serializeApprovalMeta(
            $this->fabaRecapService->getPeriodMeta($resolvedPeriod['year'], $resolvedPeriod['month']),
            $user,
        );

        return $this->success([
            'resolved_period' => $resolvedPeriod,
            'recap' => $recap,
            'approval' => $approval,
            'available_periods' => collect($this->fabaRecapService->getAvailablePeriodOptions())
                ->map(fn (array $period): array => [
                    ...$period,
                    'allowed_actions' => $this->allowedApprovalActions(
                        $this->fabaRecapService->getPeriodMeta($period['year'], $period['month']),
                        $user,
                    ),
                ])
                ->all(),
            'current_balance' => $this->fabaRecapService->getCurrentBalance(),
            'stock_card_summary' => $this->fabaRecapService->getStockCard($resolvedPeriod['year'], $resolvedPeriod['month'])['summary'],
        ], meta: [
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function monthly(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canViewRecaps($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat rekap FABA.', 'FORBIDDEN', status: 403);
        }

        $resolvedPeriod = $this->fabaRecapService->resolveRequestedOrLatestPeriod(
            $request->integer('year') ?: null,
            $request->integer('month') ?: null,
        );
        $detail = $this->fabaRecapService->getMonthlyRecapDetail($resolvedPeriod['year'], $resolvedPeriod['month']);

        return $this->success([
            'detail' => $detail,
            'approval' => $this->serializeApprovalMeta(
                $this->fabaRecapService->getPeriodMeta($resolvedPeriod['year'], $resolvedPeriod['month']),
                $user,
            ),
            'available_periods' => collect($this->fabaRecapService->getAvailablePeriodOptions())
                ->map(fn (array $period): array => [
                    ...$period,
                    'allowed_actions' => $this->allowedApprovalActions(
                        $this->fabaRecapService->getPeriodMeta($period['year'], $period['month']),
                        $user,
                    ),
                ])
                ->all(),
            'resolved_from_latest_period' => $resolvedPeriod['resolved_from_latest'],
            'filters' => [
                'year' => $resolvedPeriod['year'],
                'month' => $resolvedPeriod['month'],
            ],
        ], meta: [
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function yearly(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canViewRecaps($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat rekap tahunan FABA.', 'FORBIDDEN', status: 403);
        }

        $year = $request->integer('year', (int) now()->year);

        return $this->success($this->fabaRecapService->getYearlyRecap($year), meta: [
            'filters' => ['year' => $year],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function vendors(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canViewRecaps($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat rekap vendor FABA.', 'FORBIDDEN', status: 403);
        }

        $year = $request->integer('year', (int) now()->year);
        $vendorId = $request->string('vendor_id')->toString();

        return $this->success($this->fabaRecapService->getVendorRecap($year, $vendorId !== '' ? $vendorId : null), meta: [
            'filters' => [
                'year' => $year,
                'vendor_id' => $vendorId !== '' ? $vendorId : null,
            ],
            'options' => [
                'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canViewRecaps($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat saldo FABA.', 'FORBIDDEN', status: 403);
        }

        $resolvedPeriod = $this->fabaRecapService->resolveRequestedOrLatestPeriod(
            $request->integer('year') ?: null,
            $request->integer('month') ?: null,
        );

        return $this->success([
            'current_balance' => $this->fabaRecapService->getCurrentBalance(),
            'yearly_recap' => $this->fabaRecapService->getYearlyRecap($resolvedPeriod['year']),
            'can_manage_opening_balance' => $user->hasPermission('faba_opening_balance.manage'),
            'opening_balance_defaults' => [
                'year' => $resolvedPeriod['year'],
                'month' => $resolvedPeriod['month'],
            ],
        ], meta: [
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function stockCard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canViewRecaps($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat kartu stok FABA.', 'FORBIDDEN', status: 403);
        }

        $year = $request->integer('year', (int) now()->year);
        $month = $request->integer('month') ?: null;
        $materialType = $request->string('material_type')->toString();

        return $this->success($this->fabaRecapService->getStockCard($year, $month, $materialType !== '' ? $materialType : null), meta: [
            'filters' => [
                'year' => $year,
                'month' => $month,
                'material_type' => $materialType !== '' ? $materialType : null,
            ],
            'options' => [
                'materials' => FabaMovement::materialOptions(),
                'months' => collect(range(1, 12))->map(fn (int $item): array => [
                    'value' => $item,
                    'label' => $this->fabaRecapService->formatMonthLabel($item),
                ])->all(),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function storeOpeningBalance(StoreFabaOpeningBalanceRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_opening_balance.manage')) {
            return $this->error('Anda tidak memiliki izin untuk mengelola opening balance FABA.', 'FORBIDDEN', status: 403);
        }

        $validated = $request->validated();
        $balance = $this->fabaRecapService->setOpeningBalance(
            (int) $validated['year'],
            (int) $validated['month'],
            (string) $validated['material_type'],
            (float) $validated['quantity'],
            $validated['note'] ?? null,
            $user->id,
        );

        $this->fabaAuditService->log(
            $user->id,
            'set_opening_balance',
            FabaAuditLog::MODULE_BALANCE,
            get_class($balance),
            $balance->id,
            (int) $validated['year'],
            (int) $validated['month'],
            'Opening balance disimpan.',
            [
                'material_type' => $balance->material_type,
                'quantity' => (float) $balance->quantity,
                'note' => $balance->note,
            ],
        );

        $balance->load('setByUser:id,name');

        return $this->success([
            'id' => $balance->id,
            'year' => $balance->year,
            'month' => $balance->month,
            'period_label' => $this->fabaRecapService->formatPeriodLabel($balance->year, $balance->month),
            'material_type' => $balance->material_type,
            'quantity' => (float) $balance->quantity,
            'note' => $balance->note,
            'set_at' => $balance->set_at?->toIso8601String(),
            'set_by_user' => $balance->setByUser,
        ], 'Opening balance berhasil disimpan.');
    }

    protected function canViewAnyFabaDashboard($user): bool
    {
        return $user->hasPermission('faba_recaps.view')
            || $user->hasPermission('faba_approvals.view')
            || $user->hasPermission('faba_approvals.approve')
            || $user->hasPermission('faba_production.view')
            || $user->hasPermission('faba_utilization.view')
            || $user->hasPermission('faba_adjustments.view');
    }

    protected function canViewRecaps($user): bool
    {
        return $user->hasPermission('faba_recaps.view')
            || $user->hasPermission('faba_approvals.view')
            || $user->hasPermission('faba_approvals.approve');
    }

    /**
     * @param  array<string, mixed>  $approval
     * @return array<string, mixed>
     */
    protected function serializeApprovalMeta(array $approval, $user): array
    {
        $approval['allowed_actions'] = $this->allowedApprovalActions($approval, $user);

        return $approval;
    }

    /**
     * @param  array<string, mixed>  $approval
     * @return list<string>
     */
    protected function allowedApprovalActions(array $approval, $user): array
    {
        $actions = [];

        if ($this->canViewRecaps($user)) {
            $actions[] = 'review';
        }

        if ($user->hasPermission('faba_approvals.approve')) {
            if (($approval['can_submit'] ?? false) === true) {
                $actions[] = 'submit';
            }

            if (($approval['can_approve'] ?? false) === true) {
                $actions[] = 'approve';
            }

            if (($approval['can_reject'] ?? false) === true) {
                $actions[] = 'reject';
            }

            if (($approval['can_reopen'] ?? false) === true) {
                $actions[] = 'reopen';
            }
        }

        return $actions;
    }
}
