<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\FabaProductionMovementRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaMovement;
use App\Services\FabaAuditService;
use App\Services\FabaRecapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FabaProductionMovementsController extends Controller
{
    /**
     * @var list<string>
     */
    private const MOVEMENT_TYPES = [
        FabaMovement::TYPE_PRODUCTION,
        FabaMovement::TYPE_WORKSHOP,
        FabaMovement::TYPE_REJECT,
        FabaMovement::TYPE_DISPOSAL_POK,
    ];

    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService
    ) {}

    public function index(): Response
    {
        $entries = FabaMovement::query()
            ->whereIn('movement_type', self::MOVEMENT_TYPES)
            ->with(['createdByUser:id,name', 'updatedByUser:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FabaMovement $movement): array => $this->transformMovement($movement));

        return Inertia::render('waste-management/faba/production/Index', [
            'entries' => $entries,
            'filters' => [
                'materials' => FabaMovement::materialOptions(),
                'movementTypes' => FabaMovement::productionTypeOptions(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('waste-management/faba/production/Create', [
            'materialOptions' => FabaMovement::materialOptions(),
            'movementTypeOptionsByMaterial' => FabaMovement::productionTypeOptionsByMaterial(),
            'defaultUnit' => FabaMovement::DEFAULT_UNIT,
        ]);
    }

    public function store(FabaProductionMovementRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if ($this->fabaRecapService->isPeriodLocked($year, $month)) {
            return Redirect::back()
                ->with('error', 'Periode bulan ini sedang diajukan atau sudah disetujui, sehingga transaksi tidak bisa ditambah.');
        }

        $movement = FabaMovement::query()->create([
            'transaction_date' => $validated['transaction_date'],
            'material_type' => $validated['material_type'],
            'movement_type' => $validated['movement_type'],
            'stock_effect' => $this->resolveStockEffect($validated['movement_type']),
            'quantity' => round((float) $validated['quantity'], 2),
            'unit' => FabaMovement::DEFAULT_UNIT,
            'period_year' => $year,
            'period_month' => $month,
            'note' => $validated['note'] ?? null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $movement->load(['createdByUser:id,name', 'updatedByUser:id,name']);

        $this->fabaAuditService->log(
            Auth::id(),
            'create',
            FabaAuditLog::MODULE_MOVEMENT,
            FabaMovement::class,
            $movement->id,
            $year,
            $month,
            'Movement produksi FABA dibuat.',
            $this->transformMovement($movement)
        );

        return Redirect::route('waste-management.faba.production.show', $movement->id)
            ->with('success', 'Movement produksi FABA berhasil dibuat.');
    }

    public function show(string $production): Response
    {
        $movement = $this->findMovementOrFail($production);

        return Inertia::render('waste-management/faba/production/Show', [
            'entry' => $this->transformMovement($movement),
        ]);
    }

    public function edit(string $production): Response
    {
        $movement = $this->findMovementOrFail($production);
        $this->abortIfCannotModify($movement, 'faba_production.edit', 'mengubah');

        return Inertia::render('waste-management/faba/production/Edit', [
            'entry' => $this->transformMovement($movement),
            'materialOptions' => FabaMovement::materialOptions(),
            'movementTypeOptionsByMaterial' => FabaMovement::productionTypeOptionsByMaterial(),
            'defaultUnit' => FabaMovement::DEFAULT_UNIT,
        ]);
    }

    public function update(FabaProductionMovementRequest $request, string $production): RedirectResponse
    {
        $movement = $this->findMovementOrFail($production);
        $this->abortIfCannotModify($movement, 'faba_production.edit', 'mengubah');

        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if (
            ($year !== $movement->period_year || $month !== $movement->period_month)
            && $this->fabaRecapService->isPeriodLocked($year, $month)
        ) {
            return Redirect::back()->with('error', 'Periode tujuan sedang terkunci.');
        }

        $movement->update([
            'transaction_date' => $validated['transaction_date'],
            'material_type' => $validated['material_type'],
            'movement_type' => $validated['movement_type'],
            'stock_effect' => $this->resolveStockEffect($validated['movement_type']),
            'quantity' => round((float) $validated['quantity'], 2),
            'unit' => FabaMovement::DEFAULT_UNIT,
            'period_year' => $year,
            'period_month' => $month,
            'note' => $validated['note'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        $movement->load(['createdByUser:id,name', 'updatedByUser:id,name']);

        $this->fabaAuditService->log(
            Auth::id(),
            'update',
            FabaAuditLog::MODULE_MOVEMENT,
            FabaMovement::class,
            $movement->id,
            $year,
            $month,
            'Movement produksi FABA diperbarui.',
            $this->transformMovement($movement)
        );

        return Redirect::route('waste-management.faba.production.show', $movement->id)
            ->with('success', 'Movement produksi FABA berhasil diperbarui.');
    }

    public function destroy(string $production): RedirectResponse
    {
        $movement = $this->findMovementOrFail($production);
        $this->abortIfCannotModify($movement, 'faba_production.delete', 'menghapus');
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
            'Movement produksi FABA dihapus.',
            $entryData
        );

        return Redirect::route('waste-management.faba.production.index')
            ->with('success', 'Movement produksi FABA berhasil dihapus.');
    }

    public function exportCsv(): StreamedResponse
    {
        $year = request('year');
        $month = request('month');
        $materialType = request('material_type');
        $movementType = request('movement_type');

        $entries = FabaMovement::query()
            ->whereIn('movement_type', self::MOVEMENT_TYPES)
            ->with(['createdByUser:id,name'])
            ->when($year, fn ($query) => $query->where('period_year', (int) $year))
            ->when($month, fn ($query) => $query->where('period_month', (int) $month))
            ->when($materialType, fn ($query) => $query->where('material_type', $materialType))
            ->when($movementType, fn ($query) => $query->where('movement_type', $movementType))
            ->orderByDesc('transaction_date')
            ->get();

        return response()->stream(function () use ($entries): void {
            $file = fopen('php://output', 'w');

            if ($file === false) {
                Log::error('Failed to open CSV stream for FABA production export.');
                abort(500, 'Gagal menyiapkan file export produksi FABA.');
            }

            fputcsv($file, [
                'Movement Number',
                'Transaction Date',
                'Material Type',
                'Movement Type',
                'Period',
                'Approval Status',
                'Quantity',
                'Unit',
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
                    $this->fabaRecapService->formatPeriodLabel($entry->period_year, $entry->period_month),
                    $this->fabaRecapService->getPeriodStatus($entry->period_year, $entry->period_month),
                    (float) $entry->quantity,
                    $entry->unit,
                    $entry->note,
                    $entry->createdByUser?->name,
                    $entry->created_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="faba-production-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformMovement(FabaMovement $movement): array
    {
        $approval = $this->fabaRecapService->getMonthlyApproval($movement->period_year, $movement->period_month);

        return [
            'id' => $movement->id,
            'display_number' => $this->formatMovementNumber($movement),
            'transaction_date' => $movement->transaction_date->format('Y-m-d'),
            'material_type' => $movement->material_type,
            'movement_type' => $movement->movement_type,
            'quantity' => (float) $movement->quantity,
            'unit' => $movement->unit,
            'note' => $movement->note,
            'created_by_user' => $movement->createdByUser,
            'updated_by_user' => $movement->updatedByUser,
            'created_at' => $movement->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $movement->updated_at?->format('Y-m-d H:i:s'),
            'approval_status' => $approval?->status ?? 'draft',
            'can_edit' => $this->canModifyMovement($movement, 'faba_production.edit'),
            'period_label' => $this->fabaRecapService->formatPeriodLabel($movement->period_year, $movement->period_month),
        ];
    }

    protected function abortIfCannotModify(FabaMovement $movement, string $permission, string $action): void
    {
        if (! $this->canModifyMovement($movement, $permission)) {
            abort(403, sprintf('Anda tidak memiliki izin untuk %s movement produksi FABA ini.', $action));
        }
    }

    protected function canModifyMovement(FabaMovement $movement, string $permission): bool
    {
        $user = Auth::user();

        if (! $user?->hasPermission($permission)) {
            return false;
        }

        $status = $this->fabaRecapService->getPeriodStatus($movement->period_year, $movement->period_month);

        if (! in_array($status, ['draft', 'rejected'], true)) {
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
            ->with(['createdByUser:id,name', 'updatedByUser:id,name'])
            ->findOrFail($id);
    }

    protected function resolveStockEffect(string $movementType): string
    {
        return in_array($movementType, [FabaMovement::TYPE_REJECT, FabaMovement::TYPE_DISPOSAL_POK], true)
            ? FabaMovement::STOCK_EFFECT_OUT
            : FabaMovement::STOCK_EFFECT_IN;
    }

    protected function formatMovementNumber(FabaMovement $movement): string
    {
        return sprintf(
            'FM-PROD-%s-%s',
            $movement->transaction_date->format('Ymd'),
            strtoupper(substr(str_replace('-', '', $movement->id), -6))
        );
    }
}
