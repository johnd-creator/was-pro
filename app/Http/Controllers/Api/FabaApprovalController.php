<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\ApproveFabaMonthlyApprovalRequest;
use App\Http\Requests\WasteManagement\RejectFabaMonthlyApprovalRequest;
use App\Http\Requests\WasteManagement\ReopenFabaMonthlyApprovalRequest;
use App\Http\Requests\WasteManagement\SubmitFabaMonthlyApprovalRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use App\Services\FabaAuditService;
use App\Services\FabaRecapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FabaApprovalController extends ApiController
{
    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canViewApprovals($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat approval FABA.', 'FORBIDDEN', status: 403);
        }

        $year = $request->integer('year', (int) now()->year);

        return $this->success(
            $this->fabaRecapService->getAvailablePeriods($year)
                ->map(fn (array $period): array => $this->serializePeriod($period, $user))
                ->all(),
            meta: [
                'filters' => ['year' => $year],
                'server_time' => now()->toIso8601String(),
            ],
        );
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canViewApprovals($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat riwayat approval FABA.', 'FORBIDDEN', status: 403);
        }

        return $this->success([
            'approvals' => FabaMonthlyApproval::query()
                ->with(['submittedByUser:id,name', 'approvedByUser:id,name', 'rejectedByUser:id,name'])
                ->latest('year')
                ->latest('month')
                ->get()
                ->map(fn (FabaMonthlyApproval $approval): array => $this->serializeApprovalModel($approval, $user))
                ->all(),
            'audit_logs' => \App\Models\FabaAuditLog::query()
                ->with('actor:id,name')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (\App\Models\FabaAuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'module' => $log->module,
                    'summary' => $log->summary,
                    'details' => $log->details,
                    'actor' => $log->actor,
                    'year' => $log->year,
                    'month' => $log->month,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->all(),
        ], meta: [
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function review(Request $request, int $year, int $month): JsonResponse
    {
        $user = $request->user();

        if (! $this->canViewApprovals($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat detail approval FABA.', 'FORBIDDEN', status: 403);
        }

        $detail = $this->fabaRecapService->getMonthlyRecapDetail($year, $month);

        return $this->success([
            'approval' => $this->serializePeriod($this->fabaRecapService->getPeriodMeta($year, $month), $user),
            'recap' => $detail['recap'],
            'snapshot' => $detail['snapshot'],
            'vendor_breakdown' => $detail['vendor_breakdown'],
            'internal_destination_breakdown' => $detail['internal_destination_breakdown'],
            'purpose_breakdown' => $detail['purpose_breakdown'],
            'movements' => $detail['movements'],
            'audit_logs' => $detail['audit_logs'],
        ], meta: [
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function submit(SubmitFabaMonthlyApprovalRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_approvals.approve')) {
            return $this->error('Anda tidak memiliki izin untuk mengajukan approval FABA.', 'FORBIDDEN', status: 403);
        }

        $validated = $request->validated();
        $year = (int) $validated['year'];
        $month = (int) $validated['month'];

        if (! $this->fabaRecapService->hasTransactionsForPeriod($year, $month)) {
            return $this->error('Periode kosong tidak dapat diajukan untuk approval.', 'CONFLICT', status: 409);
        }

        if (FabaMovement::query()->forPeriod($year, $month)->pendingApproval()->exists()) {
            return $this->error('Masih ada transaksi FABA harian yang menunggu persetujuan.', 'CONFLICT', status: 409);
        }

        $approval = $this->fabaRecapService->getOrCreateMonthlyApproval($year, $month);

        if (! $approval->canSubmit()) {
            return $this->error('Periode ini tidak dapat diajukan lagi.', 'CONFLICT', status: 409);
        }

        $approval->update([
            'status' => FabaMonthlyApproval::STATUS_SUBMITTED,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_note' => null,
        ]);

        $this->fabaAuditService->log(
            $user->id,
            'submit',
            FabaAuditLog::MODULE_APPROVAL,
            FabaMonthlyApproval::class,
            $approval->id,
            $year,
            $month,
            'Periode diajukan untuk approval.',
            ['status' => FabaMonthlyApproval::STATUS_SUBMITTED],
        );

        return $this->success(
            $this->serializePeriod($this->fabaRecapService->getPeriodMeta($year, $month), $user),
            'Periode berhasil diajukan untuk approval.',
        );
    }

    public function approve(ApproveFabaMonthlyApprovalRequest $request, int $year, int $month): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_approvals.approve')) {
            return $this->error('Anda tidak memiliki izin untuk menyetujui approval FABA.', 'FORBIDDEN', status: 403);
        }

        $approval = $this->fabaRecapService->getOrCreateMonthlyApproval($year, $month);

        if (! $approval->canApprove()) {
            return $this->error('Periode ini tidak dapat disetujui.', 'CONFLICT', status: 409);
        }

        $approval->update([
            'status' => FabaMonthlyApproval::STATUS_APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
            'submitted_by' => $approval->submitted_by ?: $user->id,
            'submitted_at' => $approval->submitted_at ?: now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_note' => null,
        ]);

        $snapshot = $this->fabaRecapService->storeMonthlyClosingSnapshot($year, $month, $user->id);

        $this->fabaAuditService->log(
            $user->id,
            'approve',
            FabaAuditLog::MODULE_APPROVAL,
            FabaMonthlyApproval::class,
            $approval->id,
            $year,
            $month,
            'Periode disetujui.',
            [
                'approval_note' => $request->validated('approval_note'),
                'snapshot_id' => $snapshot?->id,
            ],
        );

        if ($snapshot) {
            $this->fabaAuditService->log(
                $user->id,
                'snapshot_generated',
                FabaAuditLog::MODULE_SNAPSHOT,
                FabaMonthlyApproval::class,
                $approval->id,
                $year,
                $month,
                'Snapshot closing bulanan dibuat.',
                ['snapshot_id' => $snapshot->id],
            );
        }

        return $this->success(
            $this->serializePeriod($this->fabaRecapService->getPeriodMeta($year, $month), $user),
            'Periode berhasil disetujui.',
        );
    }

    public function reject(RejectFabaMonthlyApprovalRequest $request, int $year, int $month): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_approvals.approve')) {
            return $this->error('Anda tidak memiliki izin untuk menolak approval FABA.', 'FORBIDDEN', status: 403);
        }

        $approval = $this->fabaRecapService->getOrCreateMonthlyApproval($year, $month);

        if (! $approval->canReject()) {
            return $this->error('Periode ini tidak dapat ditolak.', 'CONFLICT', status: 409);
        }

        $approval->update([
            'status' => FabaMonthlyApproval::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_note' => $request->validated('rejection_note'),
        ]);

        $this->fabaRecapService->deleteMonthlyClosingSnapshot($year, $month);

        $this->fabaAuditService->log(
            $user->id,
            'reject',
            FabaAuditLog::MODULE_APPROVAL,
            FabaMonthlyApproval::class,
            $approval->id,
            $year,
            $month,
            'Periode ditolak.',
            ['rejection_note' => $request->validated('rejection_note')],
        );

        return $this->success(
            $this->serializePeriod($this->fabaRecapService->getPeriodMeta($year, $month), $user),
            'Periode berhasil ditolak.',
        );
    }

    public function reopen(ReopenFabaMonthlyApprovalRequest $request, int $year, int $month): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_approvals.approve')) {
            return $this->error('Anda tidak memiliki izin untuk membuka kembali approval FABA.', 'FORBIDDEN', status: 403);
        }

        $approval = $this->fabaRecapService->getOrCreateMonthlyApproval($year, $month);

        if (! $approval->canReopen()) {
            return $this->error('Periode ini tidak dapat dibuka kembali.', 'CONFLICT', status: 409);
        }

        $approval->update([
            'status' => FabaMonthlyApproval::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_note' => $request->validated('reopen_note'),
        ]);

        $this->fabaRecapService->deleteMonthlyClosingSnapshot($year, $month);

        $this->fabaAuditService->log(
            $user->id,
            'reopen',
            FabaAuditLog::MODULE_APPROVAL,
            FabaMonthlyApproval::class,
            $approval->id,
            $year,
            $month,
            'Periode dibuka kembali untuk revisi.',
            ['reopen_note' => $request->validated('reopen_note')],
        );

        return $this->success(
            $this->serializePeriod($this->fabaRecapService->getPeriodMeta($year, $month), $user),
            'Periode berhasil dibuka kembali untuk revisi.',
        );
    }

    protected function canViewApprovals($user): bool
    {
        return $user->hasPermission('faba_approvals.view')
            || $user->hasPermission('faba_approvals.approve');
    }

    /**
     * @param  array<string, mixed>  $period
     * @return array<string, mixed>
     */
    protected function serializePeriod(array $period, $user): array
    {
        $period['allowed_actions'] = $this->allowedActions($period, $user);

        return $period;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeApprovalModel(FabaMonthlyApproval $approval, $user): array
    {
        return $this->serializePeriod([
            'id' => $approval->id,
            'year' => $approval->year,
            'month' => $approval->month,
            'period_label' => $this->fabaRecapService->formatPeriodLabel($approval->year, $approval->month),
            'status' => $approval->status,
            'operational_status' => $approval->status,
            'rejection_note' => $approval->rejection_note,
            'submitted_at' => $approval->submitted_at?->format('Y-m-d H:i:s'),
            'approved_at' => $approval->approved_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $approval->rejected_at?->format('Y-m-d H:i:s'),
            'submitted_by_user' => $approval->submittedByUser,
            'approved_by_user' => $approval->approvedByUser,
            'rejected_by_user' => $approval->rejectedByUser,
            'can_submit' => $approval->canSubmit(),
            'can_approve' => $approval->canApprove(),
            'can_reject' => $approval->canReject(),
            'can_review' => true,
            'can_reopen' => $approval->canReopen(),
        ], $user);
    }

    /**
     * @param  array<string, mixed>  $period
     * @return list<string>
     */
    protected function allowedActions(array $period, $user): array
    {
        $actions = [];

        if ($this->canViewApprovals($user)) {
            $actions[] = 'review';
        }

        if (! $user->hasPermission('faba_approvals.approve')) {
            return $actions;
        }

        if (($period['can_submit'] ?? false) === true) {
            $actions[] = 'submit';
        }

        if (($period['can_approve'] ?? false) === true) {
            $actions[] = 'approve';
        }

        if (($period['can_reject'] ?? false) === true) {
            $actions[] = 'reject';
        }

        if (($period['can_reopen'] ?? false) === true) {
            $actions[] = 'reopen';
        }

        return $actions;
    }
}
