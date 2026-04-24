<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\ApproveFabaMonthlyApprovalRequest;
use App\Http\Requests\WasteManagement\RejectFabaMonthlyApprovalRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaMovement;
use App\Services\FabaAuditService;
use App\Services\FabaMovementLedgerService;
use App\Services\FabaRecapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class FabaMovementApprovalsController extends Controller
{
    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService,
        protected FabaMovementLedgerService $fabaMovementLedgerService,
    ) {}

    public function approve(ApproveFabaMonthlyApprovalRequest $request, string $movement): RedirectResponse
    {
        $entry = $this->findMovementOrFail($movement);

        if (! Auth::user()?->hasPermission('faba_approvals.approve')) {
            abort(403, 'Anda tidak memiliki izin untuk menyetujui transaksi FABA.');
        }

        if ($this->fabaRecapService->isPeriodLocked($entry->period_year, $entry->period_month)) {
            return Redirect::back()->with('error', 'Periode transaksi sedang terkunci sehingga approval harian tidak dapat diproses.');
        }

        if (! $entry->canApprove()) {
            return Redirect::back()->with('error', 'Transaksi ini tidak berada pada status menunggu persetujuan.');
        }

        if ($entry->stock_effect === FabaMovement::STOCK_EFFECT_OUT) {
            $availableStock = $this->fabaMovementLedgerService->calculateAvailableStock(
                $entry->material_type,
                $entry->transaction_date,
            );

            if ((float) $entry->quantity > $availableStock) {
                return Redirect::back()->with('error', 'Approval ditolak sistem karena jumlah transaksi melebihi stok FABA yang telah disetujui.');
            }
        }

        $entry->update([
            'approval_status' => FabaMovement::STATUS_APPROVED,
            'submitted_by' => $entry->submitted_by ?: Auth::id(),
            'submitted_at' => $entry->submitted_at ?: now(),
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_note' => null,
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
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

        return Redirect::route($this->resolveDetailRouteName($entry), $entry->id)
            ->with('success', 'Transaksi FABA berhasil disetujui.');
    }

    public function reject(RejectFabaMonthlyApprovalRequest $request, string $movement): RedirectResponse
    {
        $entry = $this->findMovementOrFail($movement);

        if (! Auth::user()?->hasPermission('faba_approvals.reject')) {
            abort(403, 'Anda tidak memiliki izin untuk menolak transaksi FABA.');
        }

        if ($this->fabaRecapService->isPeriodLocked($entry->period_year, $entry->period_month)) {
            return Redirect::back()->with('error', 'Periode transaksi sedang terkunci sehingga penolakan tidak dapat diproses.');
        }

        if (! $entry->canReject()) {
            return Redirect::back()->with('error', 'Transaksi ini tidak berada pada status menunggu persetujuan.');
        }

        $entry->update([
            'approval_status' => FabaMovement::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'rejection_note' => $request->validated('rejection_note'),
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
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

        return Redirect::route($this->resolveDetailRouteName($entry), $entry->id)
            ->with('success', 'Transaksi FABA berhasil ditolak.');
    }

    protected function findMovementOrFail(string $id): FabaMovement
    {
        return FabaMovement::query()->findOrFail($id);
    }

    protected function resolveDetailRouteName(FabaMovement $movement): string
    {
        return match ($movement->movement_type) {
            FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            FabaMovement::TYPE_UTILIZATION_INTERNAL => 'waste-management.faba.utilization.show',
            FabaMovement::TYPE_ADJUSTMENT_IN,
            FabaMovement::TYPE_ADJUSTMENT_OUT => 'waste-management.faba.adjustments.show',
            default => 'waste-management.faba.production.show',
        };
    }
}
