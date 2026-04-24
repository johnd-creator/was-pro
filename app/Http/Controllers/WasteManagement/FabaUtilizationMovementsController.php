<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\FabaUtilizationMovementRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaInternalDestination;
use App\Models\FabaMovement;
use App\Models\FabaPurpose;
use App\Models\Vendor;
use App\Services\FabaAuditService;
use App\Services\FabaMovementLedgerService;
use App\Services\FabaRecapService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FabaUtilizationMovementsController extends Controller
{
    /**
     * @var list<string>
     */
    private const MOVEMENT_TYPES = [
        FabaMovement::TYPE_UTILIZATION_EXTERNAL,
        FabaMovement::TYPE_UTILIZATION_INTERNAL,
    ];

    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService,
        protected FabaMovementLedgerService $fabaMovementLedgerService
    ) {}

    public function index(): Response
    {
        $entries = FabaMovement::query()
            ->whereIn('movement_type', self::MOVEMENT_TYPES)
            ->with([
                'vendor:id,name',
                'internalDestination:id,name',
                'purpose:id,name',
                'createdByUser:id,name',
                'updatedByUser:id,name',
                'submittedByUser:id,name',
                'approvedByUser:id,name',
                'rejectedByUser:id,name',
            ])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FabaMovement $movement): array => $this->transformMovement($movement));

        return Inertia::render('waste-management/faba/utilization/Index', [
            'entries' => $entries,
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            'internalDestinations' => FabaInternalDestination::query()->active()->orderBy('name')->get(['id', 'name']),
            'purposes' => FabaPurpose::query()->active()->orderBy('name')->get(['id', 'name']),
            'initialMovementType' => $this->normalizeMovementType((string) request('movement_type', '')),
            'filters' => [
                'materials' => FabaMovement::materialOptions(),
                'movementTypes' => self::MOVEMENT_TYPES,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('waste-management/faba/utilization/Create', [
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            'internalDestinations' => FabaInternalDestination::query()->active()->orderBy('name')->get(['id', 'name']),
            'purposes' => FabaPurpose::query()->active()->orderBy('name')->get(['id', 'name']),
            'materialOptions' => FabaMovement::materialOptions(),
            'movementTypeOptions' => self::MOVEMENT_TYPES,
            'initialMovementType' => $this->normalizeMovementType((string) request('movement_type', ''))
                ?: FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            'defaultUnit' => FabaMovement::DEFAULT_UNIT,
            'requirements' => [
                FabaMovement::TYPE_UTILIZATION_EXTERNAL => [
                    'requiresVendor' => true,
                    'requiresDocument' => true,
                    'requiresInternalDestination' => false,
                ],
                FabaMovement::TYPE_UTILIZATION_INTERNAL => [
                    'requiresVendor' => false,
                    'requiresDocument' => false,
                    'requiresInternalDestination' => true,
                ],
            ],
        ]);
    }

    public function store(FabaUtilizationMovementRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $transactionDate = CarbonImmutable::parse($validated['transaction_date']);
        $year = (int) $transactionDate->year;
        $month = (int) $transactionDate->month;

        if ($this->fabaRecapService->isPeriodLocked($year, $month)) {
            return Redirect::back()
                ->with('error', 'Periode bulan ini sedang diajukan atau sudah disetujui, sehingga transaksi tidak bisa ditambah.');
        }

        if ($this->exceedsAvailableStock($validated, $transactionDate)) {
            return Redirect::back()
                ->withErrors([
                    'quantity' => 'Jumlah pemanfaatan melebihi stok FABA yang tersedia pada tanggal transaksi.',
                ])
                ->withInput();
        }

        $duplicateWarning = $this->fabaMovementLedgerService->getPotentialDuplicateWarning([
            'transaction_date' => $validated['transaction_date'],
            'material_type' => $validated['material_type'],
            'movement_type' => $validated['movement_type'],
            'vendor_id' => $validated['vendor_id'] ?? null,
            'internal_destination_id' => $validated['internal_destination_id'] ?? null,
            'document_number' => $validated['document_number'] ?? null,
            'document_date' => $validated['document_date'] ?? null,
            'quantity' => round((float) $validated['quantity'], 2),
        ]);

        $attachmentPath = null;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                'faba/utilization-attachments',
                config('filesystems.default')
            );
        }

        $movement = FabaMovement::query()->create([
            'transaction_date' => $validated['transaction_date'],
            'material_type' => $validated['material_type'],
            'movement_type' => $validated['movement_type'],
            'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
            'quantity' => round((float) $validated['quantity'], 2),
            'unit' => FabaMovement::DEFAULT_UNIT,
            'vendor_id' => $validated['vendor_id'] ?? null,
            'internal_destination_id' => $validated['internal_destination_id'] ?? null,
            'purpose_id' => $validated['purpose_id'] ?? null,
            'document_number' => $validated['document_number'] ?? null,
            'document_date' => $validated['document_date'] ?? null,
            'attachment_path' => $attachmentPath,
            'period_year' => $year,
            'period_month' => $month,
            'approval_status' => FabaMovement::STATUS_PENDING_APPROVAL,
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
            'note' => $validated['note'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $movement->load([
            'vendor:id,name',
            'internalDestination:id,name',
            'purpose:id,name',
            'createdByUser:id,name',
            'updatedByUser:id,name',
            'submittedByUser:id,name',
            'approvedByUser:id,name',
            'rejectedByUser:id,name',
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'create',
            FabaAuditLog::MODULE_MOVEMENT,
            FabaMovement::class,
            $movement->id,
            $year,
            $month,
            'Movement pemanfaatan FABA dibuat.',
            $this->transformMovement($movement)
        );

        return Redirect::route('waste-management.faba.utilization.show', $movement->id)
            ->with('success', 'Movement pemanfaatan FABA berhasil dibuat.')
            ->with('warning', $duplicateWarning['message'] ?? null);
    }

    public function show(string $utilization): Response
    {
        $movement = $this->findMovementOrFail($utilization);

        return Inertia::render('waste-management/faba/utilization/Show', [
            'entry' => $this->transformMovement($movement),
        ]);
    }

    public function edit(string $utilization): Response
    {
        $movement = $this->findMovementOrFail($utilization);
        $this->abortIfCannotModify($movement, 'faba_utilization.edit', 'mengubah');

        return Inertia::render('waste-management/faba/utilization/Edit', [
            'entry' => $this->transformMovement($movement),
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            'internalDestinations' => FabaInternalDestination::query()->active()->orderBy('name')->get(['id', 'name']),
            'purposes' => FabaPurpose::query()->active()->orderBy('name')->get(['id', 'name']),
            'materialOptions' => FabaMovement::materialOptions(),
            'movementTypeOptions' => self::MOVEMENT_TYPES,
            'defaultUnit' => FabaMovement::DEFAULT_UNIT,
            'requirements' => [
                FabaMovement::TYPE_UTILIZATION_EXTERNAL => [
                    'requiresVendor' => true,
                    'requiresDocument' => true,
                    'requiresInternalDestination' => false,
                ],
                FabaMovement::TYPE_UTILIZATION_INTERNAL => [
                    'requiresVendor' => false,
                    'requiresDocument' => false,
                    'requiresInternalDestination' => true,
                ],
            ],
        ]);
    }

    public function update(FabaUtilizationMovementRequest $request, string $utilization): RedirectResponse
    {
        $movement = $this->findMovementOrFail($utilization);
        $this->abortIfCannotModify($movement, 'faba_utilization.edit', 'mengubah');

        $validated = $request->validated();
        $transactionDate = CarbonImmutable::parse($validated['transaction_date']);
        $year = (int) $transactionDate->year;
        $month = (int) $transactionDate->month;

        if (
            ($year !== $movement->period_year || $month !== $movement->period_month)
            && $this->fabaRecapService->isPeriodLocked($year, $month)
        ) {
            return Redirect::back()->with('error', 'Periode tujuan sedang terkunci.');
        }

        if ($this->exceedsAvailableStock($validated, $transactionDate, $movement->id)) {
            return Redirect::back()
                ->withErrors([
                    'quantity' => 'Jumlah pemanfaatan melebihi stok FABA yang tersedia pada tanggal transaksi.',
                ])
                ->withInput();
        }

        $duplicateWarning = $this->fabaMovementLedgerService->getPotentialDuplicateWarning([
            'transaction_date' => $validated['transaction_date'],
            'material_type' => $validated['material_type'],
            'movement_type' => $validated['movement_type'],
            'vendor_id' => $validated['vendor_id'] ?? null,
            'internal_destination_id' => $validated['internal_destination_id'] ?? null,
            'document_number' => $validated['document_number'] ?? null,
            'document_date' => $validated['document_date'] ?? null,
            'quantity' => round((float) $validated['quantity'], 2),
        ], $movement->id);

        $attachmentPath = $movement->attachment_path;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                'faba/utilization-attachments',
                config('filesystems.default')
            );
        }

        $movement->update([
            'transaction_date' => $validated['transaction_date'],
            'material_type' => $validated['material_type'],
            'movement_type' => $validated['movement_type'],
            'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
            'quantity' => round((float) $validated['quantity'], 2),
            'unit' => FabaMovement::DEFAULT_UNIT,
            'vendor_id' => $validated['vendor_id'] ?? null,
            'internal_destination_id' => $validated['internal_destination_id'] ?? null,
            'purpose_id' => $validated['purpose_id'] ?? null,
            'document_number' => $validated['document_number'] ?? null,
            'document_date' => $validated['document_date'] ?? null,
            'attachment_path' => $attachmentPath,
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
            'note' => $validated['note'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        $movement->load([
            'vendor:id,name',
            'internalDestination:id,name',
            'purpose:id,name',
            'createdByUser:id,name',
            'updatedByUser:id,name',
            'submittedByUser:id,name',
            'approvedByUser:id,name',
            'rejectedByUser:id,name',
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'update',
            FabaAuditLog::MODULE_MOVEMENT,
            FabaMovement::class,
            $movement->id,
            $year,
            $month,
            'Movement pemanfaatan FABA diperbarui.',
            $this->transformMovement($movement)
        );

        return Redirect::route('waste-management.faba.utilization.show', $movement->id)
            ->with('success', 'Movement pemanfaatan FABA berhasil diperbarui.')
            ->with('warning', $duplicateWarning['message'] ?? null);
    }

    protected function exceedsAvailableStock(array $validated, CarbonImmutable $transactionDate, ?string $excludeMovementId = null): bool
    {
        $availableStock = $this->fabaMovementLedgerService->calculateAvailableStock(
            $validated['material_type'],
            $transactionDate,
            $excludeMovementId
        );

        return round((float) $validated['quantity'], 2) > $availableStock;
    }

    public function destroy(string $utilization): RedirectResponse
    {
        $movement = $this->findMovementOrFail($utilization);
        $this->abortIfCannotModify($movement, 'faba_utilization.delete', 'menghapus');
        $entryData = $this->transformMovement($movement);
        $year = $movement->period_year;
        $month = $movement->period_month;
        $movementId = $movement->id;
        $movement->delete();

        $this->fabaAuditService->log(
            Auth::id(),
            'delete',
            FabaAuditLog::MODULE_MOVEMENT,
            FabaMovement::class,
            $movementId,
            $year,
            $month,
            'Movement pemanfaatan FABA dihapus.',
            $entryData
        );

        return Redirect::route('waste-management.faba.utilization.index')
            ->with('success', 'Movement pemanfaatan FABA berhasil dihapus.');
    }

    public function exportCsv(): StreamedResponse
    {
        $year = request('year');
        $month = request('month');
        $materialType = request('material_type');
        $movementType = $this->normalizeMovementType((string) request('movement_type', ''));
        $vendorId = request('vendor_id');

        $entries = FabaMovement::query()
            ->whereIn('movement_type', self::MOVEMENT_TYPES)
            ->with([
                'vendor:id,name',
                'internalDestination:id,name',
                'purpose:id,name',
                'createdByUser:id,name',
            ])
            ->when($year, fn ($query) => $query->where('period_year', (int) $year))
            ->when($month, fn ($query) => $query->where('period_month', (int) $month))
            ->when($materialType, fn ($query) => $query->where('material_type', $materialType))
            ->when($movementType, fn ($query) => $query->where('movement_type', $movementType))
            ->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId))
            ->orderByDesc('transaction_date')
            ->get();

        return response()->stream(function () use ($entries): void {
            $file = fopen('php://output', 'w');

            if ($file === false) {
                Log::error('Failed to open CSV stream for FABA utilization export.');
                abort(500, 'Gagal menyiapkan file export pemanfaatan FABA.');
            }

            fputcsv($file, [
                'Movement Number',
                'Transaction Date',
                'Material Type',
                'Movement Type',
                'Vendor',
                'Internal Destination',
                'Purpose',
                'Period',
                'Approval Status',
                'Quantity',
                'Unit',
                'Document Number',
                'Document Date',
                'Attachment Path',
                'Note',
                'Created By',
                'Created At',
            ]);

            foreach ($entries as $entry) {
                fputcsv($file, [
                    $this->formatMovementNumber($entry),
                    $entry->transaction_date->format('Y-m-d'),
                    $entry->material_type,
                    $entry->movement_type,
                    $entry->vendor?->name,
                    $entry->internalDestination?->name,
                    $entry->purpose?->name,
                    $this->fabaRecapService->formatPeriodLabel($entry->period_year, $entry->period_month),
                    $entry->approval_status,
                    (float) $entry->quantity,
                    $entry->unit,
                    $entry->document_number,
                    $entry->document_date?->format('Y-m-d'),
                    $entry->attachment_path,
                    $entry->note,
                    $entry->createdByUser?->name,
                    $entry->created_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="faba-utilization-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformMovement(FabaMovement $movement): array
    {
        $state = $this->fabaRecapService->getMovementState($movement);
        $duplicateWarning = $this->fabaMovementLedgerService->getPotentialDuplicateWarning([
            'transaction_date' => $movement->transaction_date->format('Y-m-d'),
            'material_type' => $movement->material_type,
            'movement_type' => $movement->movement_type,
            'vendor_id' => $movement->vendor_id,
            'internal_destination_id' => $movement->internal_destination_id,
            'document_number' => $movement->document_number,
            'document_date' => $movement->document_date?->format('Y-m-d'),
            'quantity' => round((float) $movement->quantity, 2),
        ], $movement->id);

        return [
            'id' => $movement->id,
            'display_number' => $this->formatMovementNumber($movement),
            'transaction_date' => $movement->transaction_date->format('Y-m-d'),
            'material_type' => $movement->material_type,
            'movement_type' => $movement->movement_type,
            'vendor_id' => $movement->vendor_id,
            'vendor' => $movement->vendor,
            'internal_destination_id' => $movement->internal_destination_id,
            'internal_destination' => $movement->internalDestination,
            'purpose_id' => $movement->purpose_id,
            'purpose' => $movement->purpose,
            'quantity' => (float) $movement->quantity,
            'unit' => $movement->unit,
            'document_number' => $movement->document_number,
            'document_date' => $movement->document_date?->format('Y-m-d'),
            'attachment_path' => $movement->attachment_path,
            'note' => $movement->note,
            'created_by_user' => $movement->createdByUser,
            'updated_by_user' => $movement->updatedByUser,
            'created_at' => $movement->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $movement->updated_at?->format('Y-m-d H:i:s'),
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
            'can_edit' => $this->canModifyMovement($movement, 'faba_utilization.edit'),
            'period_label' => $this->fabaRecapService->formatPeriodLabel($movement->period_year, $movement->period_month),
        ];
    }

    protected function abortIfCannotModify(FabaMovement $movement, string $permission, string $action): void
    {
        if (! $this->canModifyMovement($movement, $permission)) {
            abort(403, sprintf('Anda tidak memiliki izin untuk %s movement pemanfaatan FABA ini.', $action));
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

    protected function findMovementOrFail(string $id): FabaMovement
    {
        return FabaMovement::query()
            ->whereIn('movement_type', self::MOVEMENT_TYPES)
            ->with([
                'vendor:id,name',
                'internalDestination:id,name',
                'purpose:id,name',
                'createdByUser:id,name',
                'updatedByUser:id,name',
                'submittedByUser:id,name',
                'approvedByUser:id,name',
                'rejectedByUser:id,name',
            ])
            ->findOrFail($id);
    }

    protected function normalizeMovementType(string $movementType): ?string
    {
        return match ($movementType) {
            'internal', FabaMovement::TYPE_UTILIZATION_INTERNAL => FabaMovement::TYPE_UTILIZATION_INTERNAL,
            'external', FabaMovement::TYPE_UTILIZATION_EXTERNAL => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            default => null,
        };
    }

    protected function formatMovementNumber(FabaMovement $movement): string
    {
        return sprintf(
            'FM-UTL-%s-%s',
            $movement->transaction_date->format('Ymd'),
            strtoupper(substr(str_replace('-', '', $movement->id), -6))
        );
    }
}
