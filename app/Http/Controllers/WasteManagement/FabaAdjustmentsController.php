<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\FabaAdjustmentRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaMovement;
use App\Services\FabaAuditService;
use App\Services\FabaMovementLedgerService;
use App\Services\FabaRecapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class FabaAdjustmentsController extends Controller
{
    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService,
        protected FabaMovementLedgerService $fabaMovementLedgerService,
    ) {}

    public function index(): Response
    {
        $adjustments = FabaMovement::query()
            ->whereIn('movement_type', [FabaMovement::TYPE_ADJUSTMENT_IN, FabaMovement::TYPE_ADJUSTMENT_OUT])
            ->with(['createdByUser:id,name', 'updatedByUser:id,name', 'submittedByUser:id,name', 'approvedByUser:id,name', 'rejectedByUser:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FabaMovement $movement): array => $this->transformMovement($movement));

        return Inertia::render('waste-management/faba/adjustments/Index', [
            'entries' => $adjustments,
            'filters' => [
                'materials' => FabaMovement::materialOptions(),
                'movementTypes' => [FabaMovement::TYPE_ADJUSTMENT_IN, FabaMovement::TYPE_ADJUSTMENT_OUT],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('waste-management/faba/adjustments/Create', [
            'materialOptions' => FabaMovement::materialOptions(),
            'movementTypeOptions' => [FabaMovement::TYPE_ADJUSTMENT_IN, FabaMovement::TYPE_ADJUSTMENT_OUT],
            'defaultUnit' => FabaMovement::DEFAULT_UNIT,
        ]);
    }

    public function store(FabaAdjustmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if ($this->fabaRecapService->isPeriodLocked($year, $month)) {
            return Redirect::back()->with('error', 'Periode tujuan sedang terkunci. Gunakan reopen sebelum melakukan koreksi.');
        }

        if ($validated['movement_type'] === FabaMovement::TYPE_ADJUSTMENT_OUT) {
            $availableStock = $this->fabaMovementLedgerService->calculateAvailableStock(
                $validated['material_type'],
                now()->parse($validated['transaction_date'])
            );

            if ($availableStock < (float) $validated['quantity']) {
                return Redirect::back()->withErrors([
                    'quantity' => 'Adjustment keluar melebihi stok tersedia.',
                ]);
            }
        }

        $duplicateWarning = $this->fabaMovementLedgerService->getPotentialDuplicateWarning([
            'transaction_date' => $validated['transaction_date'],
            'material_type' => $validated['material_type'],
            'movement_type' => $validated['movement_type'],
            'quantity' => round((float) $validated['quantity'], 2),
        ]);

        $movement = FabaMovement::query()->create([
            'transaction_date' => $validated['transaction_date'],
            'material_type' => $validated['material_type'],
            'movement_type' => $validated['movement_type'],
            'stock_effect' => $validated['movement_type'] === FabaMovement::TYPE_ADJUSTMENT_IN
                ? FabaMovement::STOCK_EFFECT_IN
                : FabaMovement::STOCK_EFFECT_OUT,
            'quantity' => round((float) $validated['quantity'], 2),
            'unit' => FabaMovement::DEFAULT_UNIT,
            'period_year' => $year,
            'period_month' => $month,
            'approval_status' => FabaMovement::STATUS_PENDING_APPROVAL,
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
            'note' => $validated['note'],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'create',
            FabaAuditLog::MODULE_ADJUSTMENT,
            FabaMovement::class,
            $movement->id,
            $year,
            $month,
            'Adjustment FABA dibuat.',
            $this->transformMovement($movement->fresh(['createdByUser:id,name', 'updatedByUser:id,name']))
        );

        return Redirect::route('waste-management.faba.adjustments.show', $movement->id)
            ->with('success', 'Adjustment FABA berhasil dibuat.')
            ->with('warning', $duplicateWarning['message'] ?? null);
    }

    public function show(string $adjustment): Response
    {
        return Inertia::render('waste-management/faba/adjustments/Show', [
            'entry' => $this->transformMovement($this->findMovementOrFail($adjustment)),
        ]);
    }

    public function edit(string $adjustment): Response
    {
        $movement = $this->findMovementOrFail($adjustment);
        $this->abortIfCannotModify($movement, 'faba_adjustments.edit', 'mengubah');

        return Inertia::render('waste-management/faba/adjustments/Edit', [
            'entry' => $this->transformMovement($movement),
            'materialOptions' => FabaMovement::materialOptions(),
            'movementTypeOptions' => [FabaMovement::TYPE_ADJUSTMENT_IN, FabaMovement::TYPE_ADJUSTMENT_OUT],
            'defaultUnit' => FabaMovement::DEFAULT_UNIT,
        ]);
    }

    public function update(FabaAdjustmentRequest $request, string $adjustment): RedirectResponse
    {
        $movement = $this->findMovementOrFail($adjustment);
        $this->abortIfCannotModify($movement, 'faba_adjustments.edit', 'mengubah');

        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if (($year !== $movement->period_year || $month !== $movement->period_month) && $this->fabaRecapService->isPeriodLocked($year, $month)) {
            return Redirect::back()->with('error', 'Periode tujuan sedang terkunci.');
        }

        if ($validated['movement_type'] === FabaMovement::TYPE_ADJUSTMENT_OUT) {
            $availableStock = $this->fabaMovementLedgerService->calculateAvailableStock(
                $validated['material_type'],
                now()->parse($validated['transaction_date']),
                $movement->id,
            );

            if ($availableStock < (float) $validated['quantity']) {
                return Redirect::back()->withErrors([
                    'quantity' => 'Adjustment keluar melebihi stok tersedia.',
                ]);
            }
        }

        $duplicateWarning = $this->fabaMovementLedgerService->getPotentialDuplicateWarning([
            'transaction_date' => $validated['transaction_date'],
            'material_type' => $validated['material_type'],
            'movement_type' => $validated['movement_type'],
            'quantity' => round((float) $validated['quantity'], 2),
        ], $movement->id);

        $movement->update([
            'transaction_date' => $validated['transaction_date'],
            'material_type' => $validated['material_type'],
            'movement_type' => $validated['movement_type'],
            'stock_effect' => $validated['movement_type'] === FabaMovement::TYPE_ADJUSTMENT_IN
                ? FabaMovement::STOCK_EFFECT_IN
                : FabaMovement::STOCK_EFFECT_OUT,
            'quantity' => round((float) $validated['quantity'], 2),
            'unit' => FabaMovement::DEFAULT_UNIT,
            'period_year' => $year,
            'period_month' => $month,
            'approval_status' => FabaMovement::STATUS_PENDING_APPROVAL,
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_note' => null,
            'note' => $validated['note'],
            'updated_by' => Auth::id(),
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'update',
            FabaAuditLog::MODULE_ADJUSTMENT,
            FabaMovement::class,
            $movement->id,
            $year,
            $month,
            'Adjustment FABA diperbarui.',
            $this->transformMovement($movement->fresh(['createdByUser:id,name', 'updatedByUser:id,name']))
        );

        return Redirect::route('waste-management.faba.adjustments.show', $movement->id)
            ->with('success', 'Adjustment FABA berhasil diperbarui.')
            ->with('warning', $duplicateWarning['message'] ?? null);
    }

    public function destroy(string $adjustment): RedirectResponse
    {
        $movement = $this->findMovementOrFail($adjustment);
        $this->abortIfCannotModify($movement, 'faba_adjustments.delete', 'menghapus');
        $entryData = $this->transformMovement($movement);
        $movementId = $movement->id;
        $year = $movement->period_year;
        $month = $movement->period_month;
        $movement->delete();

        $this->fabaAuditService->log(
            Auth::id(),
            'delete',
            FabaAuditLog::MODULE_ADJUSTMENT,
            FabaMovement::class,
            $movementId,
            $year,
            $month,
            'Adjustment FABA dihapus.',
            $entryData
        );

        return Redirect::route('waste-management.faba.adjustments.index')
            ->with('success', 'Adjustment FABA berhasil dihapus.');
    }

    protected function abortIfLocked(FabaMovement $movement): void
    {
        if ($this->fabaRecapService->isPeriodLocked($movement->period_year, $movement->period_month)) {
            abort(403, 'Periode transaksi ini sedang terkunci.');
        }
    }

    protected function findMovementOrFail(string $id): FabaMovement
    {
        return FabaMovement::query()
            ->whereIn('movement_type', [FabaMovement::TYPE_ADJUSTMENT_IN, FabaMovement::TYPE_ADJUSTMENT_OUT])
            ->with(['createdByUser:id,name', 'updatedByUser:id,name', 'submittedByUser:id,name', 'approvedByUser:id,name', 'rejectedByUser:id,name'])
            ->findOrFail($id);
    }

    protected function transformMovement(FabaMovement $movement): array
    {
        $state = $this->fabaRecapService->getMovementState($movement);
        $duplicateWarning = $this->fabaMovementLedgerService->getPotentialDuplicateWarning([
            'transaction_date' => $movement->transaction_date->format('Y-m-d'),
            'material_type' => $movement->material_type,
            'movement_type' => $movement->movement_type,
            'quantity' => round((float) $movement->quantity, 2),
        ], $movement->id);

        return [
            'id' => $movement->id,
            'display_number' => sprintf('FAD-%s-%s', $movement->transaction_date->format('Ym'), strtoupper(substr(str_replace('-', '', $movement->id), -4))),
            'transaction_date' => $movement->transaction_date->format('Y-m-d'),
            'material_type' => $movement->material_type,
            'movement_type' => $movement->movement_type,
            'quantity' => (float) $movement->quantity,
            'unit' => $movement->unit,
            'note' => $movement->note,
            'period_label' => $this->fabaRecapService->formatPeriodLabel($movement->period_year, $movement->period_month),
            'approval_status' => $movement->approval_status,
            'period_status' => $state['period_status'],
            'period_operational_status' => $state['period_operational_status'],
            'locked' => $state['locked'],
            'effective_status' => $state['effective_status'],
            'submitted_by_user' => $movement->submittedByUser,
            'approved_by_user' => $movement->approvedByUser,
            'rejected_by_user' => $movement->rejectedByUser,
            'submitted_at' => $movement->submitted_at?->format('Y-m-d H:i:s'),
            'approved_at' => $movement->approved_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $movement->rejected_at?->format('Y-m-d H:i:s'),
            'rejection_note' => $movement->rejection_note,
            'duplicate_warning' => $duplicateWarning,
            'can_approve' => Auth::user()?->hasPermission('faba_approvals.approve') && $movement->canApprove() && ! $this->fabaRecapService->isPeriodLocked($movement->period_year, $movement->period_month),
            'can_reject' => Auth::user()?->hasPermission('faba_approvals.reject') && $movement->canReject() && ! $this->fabaRecapService->isPeriodLocked($movement->period_year, $movement->period_month),
            'can_edit' => $this->canModifyMovement($movement, 'faba_adjustments.edit'),
            'created_by_user' => $movement->createdByUser,
            'updated_by_user' => $movement->updatedByUser,
        ];
    }

    protected function abortIfCannotModify(FabaMovement $movement, string $permission, string $action): void
    {
        if (! $this->canModifyMovement($movement, $permission)) {
            abort(403, sprintf('Anda tidak memiliki izin untuk %s adjustment FABA ini.', $action));
        }
    }

    protected function canModifyMovement(FabaMovement $movement, string $permission): bool
    {
        $user = Auth::user();

        if (! $user?->hasPermission($permission)) {
            return false;
        }

        if ($this->fabaRecapService->isPeriodLocked($movement->period_year, $movement->period_month)) {
            return false;
        }

        if (! in_array($movement->approval_status, [FabaMovement::STATUS_PENDING_APPROVAL, FabaMovement::STATUS_REJECTED], true)) {
            return false;
        }

        if ($user->hasRole('operator')) {
            return (string) $movement->created_by === (string) $user->id;
        }

        return true;
    }
}
