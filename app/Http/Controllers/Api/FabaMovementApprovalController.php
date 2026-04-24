<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\ApproveFabaMonthlyApprovalRequest;
use App\Http\Requests\WasteManagement\RejectFabaMonthlyApprovalRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaMovement;
use App\Services\FabaAuditService;
use App\Services\FabaMovementLedgerService;
use App\Services\FabaRecapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FabaMovementApprovalController extends ApiController
{
    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService,
        protected FabaMovementLedgerService $fabaMovementLedgerService,
    ) {}

    public function approve(ApproveFabaMonthlyApprovalRequest $request, string $movement): JsonResponse
    {
        $user = $request->user();
        $entry = FabaMovement::query()->findOrFail($movement);

        if (! $user->hasPermission('faba_approvals.approve')) {
            return $this->error('Anda tidak memiliki izin untuk menyetujui transaksi FABA.', 'FORBIDDEN', status: 403);
        }

        if ($this->fabaRecapService->isPeriodLocked($entry->period_year, $entry->period_month)) {
            return $this->error('Periode transaksi sedang terkunci.', 'CONFLICT', status: 409);
        }

        if (! $entry->canApprove()) {
            return $this->error('Transaksi ini tidak berada pada status menunggu persetujuan.', 'CONFLICT', status: 409);
        }

        if ($entry->stock_effect === FabaMovement::STOCK_EFFECT_OUT) {
            $availableStock = $this->fabaMovementLedgerService->calculateAvailableStock(
                $entry->material_type,
                $entry->transaction_date,
            );

            if ((float) $entry->quantity > $availableStock) {
                return $this->error('Approval ditolak sistem karena jumlah transaksi melebihi stok FABA yang telah disetujui.', 'CONFLICT', status: 409);
            }
        }

        $entry->update([
            'approval_status' => FabaMovement::STATUS_APPROVED,
            'submitted_by' => $entry->submitted_by ?: $user->id,
            'submitted_at' => $entry->submitted_at ?: now(),
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_note' => null,
        ]);

        $this->fabaAuditService->log(
            $user->id,
            'approve_transaction',
            FabaAuditLog::MODULE_APPROVAL,
            FabaMovement::class,
            $entry->id,
            $entry->period_year,
            $entry->period_month,
            'Transaksi FABA disetujui.',
            [
                'movement_type' => $entry->movement_type,
                'approval_note' => $request->validated('approval_note'),
            ]
        );

        return $this->success([
            'id' => $entry->id,
            'approval_status' => $entry->approval_status,
            'approved_at' => $entry->approved_at?->toIso8601String(),
        ], 'Transaksi FABA berhasil disetujui.');
    }

    public function reject(RejectFabaMonthlyApprovalRequest $request, string $movement): JsonResponse
    {
        $user = $request->user();
        $entry = FabaMovement::query()->findOrFail($movement);

        if (! $user->hasPermission('faba_approvals.reject')) {
            return $this->error('Anda tidak memiliki izin untuk menolak transaksi FABA.', 'FORBIDDEN', status: 403);
        }

        if ($this->fabaRecapService->isPeriodLocked($entry->period_year, $entry->period_month)) {
            return $this->error('Periode transaksi sedang terkunci.', 'CONFLICT', status: 409);
        }

        if (! $entry->canReject()) {
            return $this->error('Transaksi ini tidak berada pada status menunggu persetujuan.', 'CONFLICT', status: 409);
        }

        $entry->update([
            'approval_status' => FabaMovement::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_note' => $request->validated('rejection_note'),
        ]);

        $this->fabaAuditService->log(
            $user->id,
            'reject_transaction',
            FabaAuditLog::MODULE_APPROVAL,
            FabaMovement::class,
            $entry->id,
            $entry->period_year,
            $entry->period_month,
            'Transaksi FABA ditolak.',
            [
                'movement_type' => $entry->movement_type,
                'rejection_note' => $request->validated('rejection_note'),
            ]
        );

        return $this->success([
            'id' => $entry->id,
            'approval_status' => $entry->approval_status,
            'rejected_at' => $entry->rejected_at?->toIso8601String(),
            'rejection_note' => $entry->rejection_note,
        ], 'Transaksi FABA berhasil ditolak.');
    }

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_approvals.view')) {
            return $this->error('Anda tidak memiliki izin untuk melihat antrean approval transaksi FABA.', 'FORBIDDEN', status: 403);
        }

        $entries = FabaMovement::query()
            ->pendingApproval()
            ->with(['createdByUser:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (FabaMovement $movement): array => [
                'id' => $movement->id,
                'movement_type' => $movement->movement_type,
                'material_type' => $movement->material_type,
                'quantity' => (float) $movement->quantity,
                'unit' => $movement->unit,
                'transaction_date' => $movement->transaction_date->format('Y-m-d'),
                'period_year' => $movement->period_year,
                'period_month' => $movement->period_month,
                'approval_status' => $movement->approval_status,
                'submitted_at' => $movement->submitted_at?->toIso8601String(),
                'created_by_user' => $movement->createdByUser,
            ])
            ->values();

        return $this->success($entries->all(), meta: [
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
