<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\FabaProductionMovementRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaMovement;
use App\Models\User;
use App\Services\FabaAuditService;
use App\Services\FabaRecapService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FabaProductionController extends ApiController
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
        protected FabaAuditService $fabaAuditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_production.view')) {
            return $this->error('Anda tidak memiliki izin untuk melihat data produksi FABA.', 'FORBIDDEN', status: 403);
        }

        $query = FabaMovement::query()
            ->whereIn('movement_type', self::MOVEMENT_TYPES)
            ->with(['createdByUser:id,name', 'updatedByUser:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at');

        $materialType = $request->string('material_type')->toString();
        $movementType = $request->string('movement_type')->toString();
        $year = $request->integer('year');
        $month = $request->integer('month');

        if ($materialType !== '') {
            $query->where('material_type', $materialType);
        }

        if ($movementType !== '') {
            $query->where('movement_type', $movementType);
        }

        if ($year > 0) {
            $query->where('period_year', $year);
        }

        if ($month > 0) {
            $query->where('period_month', $month);
        }

        /** @var LengthAwarePaginator $entries */
        $entries = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return $this->success(
            collect($entries->items())->map(fn (FabaMovement $movement): array => $this->serializeMovement($movement, $user))->all(),
            meta: [
                'pagination' => $this->paginationMeta($entries),
                'filters' => [
                    'material_type' => $materialType !== '' ? $materialType : null,
                    'movement_type' => $movementType !== '' ? $movementType : null,
                    'year' => $year > 0 ? $year : null,
                    'month' => $month > 0 ? $month : null,
                ],
                'server_time' => now()->toIso8601String(),
            ],
        );
    }

    public function store(FabaProductionMovementRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_production.create')) {
            return $this->error('Anda tidak memiliki izin untuk membuat movement produksi FABA.', 'FORBIDDEN', status: 403);
        }

        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if ($this->fabaRecapService->isPeriodLocked($year, $month)) {
            return $this->error('Periode bulan ini sedang diajukan atau sudah disetujui, sehingga transaksi tidak bisa ditambah.', 'CONFLICT', status: 409);
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
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $movement->load(['createdByUser:id,name', 'updatedByUser:id,name']);

        $this->fabaAuditService->log(
            $user->id,
            'create',
            FabaAuditLog::MODULE_PRODUCTION,
            FabaMovement::class,
            $movement->id,
            $year,
            $month,
            'Movement produksi FABA dibuat.',
            $this->serializeMovement($movement, $user)
        );

        return $this->success($this->serializeMovement($movement, $user), 'Movement produksi FABA berhasil dibuat.', status: 201);
    }

    public function show(Request $request, string $production): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_production.view')) {
            return $this->error('Anda tidak memiliki izin untuk melihat movement produksi FABA.', 'FORBIDDEN', status: 403);
        }

        return $this->success($this->serializeMovement($this->findMovementOrFail($production), $user));
    }

    public function update(FabaProductionMovementRequest $request, string $production): JsonResponse
    {
        $user = $request->user();
        $movement = $this->findMovementOrFail($production);

        if (! $this->canAccessMovement($movement, $user, 'faba_production.edit')) {
            return $this->error('Anda tidak memiliki izin untuk mengubah movement produksi FABA ini.', 'FORBIDDEN', status: 403);
        }

        if ($this->fabaRecapService->isPeriodLocked($movement->period_year, $movement->period_month)) {
            return $this->error('Periode transaksi ini sedang terkunci.', 'CONFLICT', status: 409);
        }

        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if (($year !== $movement->period_year || $month !== $movement->period_month) && $this->fabaRecapService->isPeriodLocked($year, $month)) {
            return $this->error('Periode tujuan sedang terkunci.', 'CONFLICT', status: 409);
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
            'updated_by' => $user->id,
        ]);

        $movement->load(['createdByUser:id,name', 'updatedByUser:id,name']);

        $this->fabaAuditService->log(
            $user->id,
            'update',
            FabaAuditLog::MODULE_PRODUCTION,
            FabaMovement::class,
            $movement->id,
            $year,
            $month,
            'Movement produksi FABA diperbarui.',
            $this->serializeMovement($movement, $user)
        );

        return $this->success($this->serializeMovement($movement, $user), 'Movement produksi FABA berhasil diperbarui.');
    }

    public function destroy(Request $request, string $production): JsonResponse
    {
        $user = $request->user();
        $movement = $this->findMovementOrFail($production);

        if (! $this->canAccessMovement($movement, $user, 'faba_production.delete')) {
            return $this->error('Anda tidak memiliki izin untuk menghapus movement produksi FABA ini.', 'FORBIDDEN', status: 403);
        }

        if ($this->fabaRecapService->isPeriodLocked($movement->period_year, $movement->period_month)) {
            return $this->error('Periode transaksi ini sedang terkunci.', 'CONFLICT', status: 409);
        }

        $payload = $this->serializeMovement($movement, $user);
        $movementId = $movement->id;
        $year = $movement->period_year;
        $month = $movement->period_month;
        $movement->delete();

        $this->fabaAuditService->log(
            $user->id,
            'delete',
            FabaAuditLog::MODULE_PRODUCTION,
            FabaMovement::class,
            $movementId,
            $year,
            $month,
            'Movement produksi FABA dihapus.',
            $payload
        );

        return $this->success(message: 'Movement produksi FABA berhasil dihapus.');
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

    /**
     * @return array<string, mixed>
     */
    protected function serializeMovement(FabaMovement $movement, $user): array
    {
        return [
            'id' => $movement->id,
            'display_number' => sprintf('FM-PROD-%s-%s', $movement->transaction_date->format('Ymd'), strtoupper(substr(str_replace('-', '', $movement->id), -6))),
            'transaction_date' => $movement->transaction_date->format('Y-m-d'),
            'material_type' => $movement->material_type,
            'movement_type' => $movement->movement_type,
            'quantity' => (float) $movement->quantity,
            'unit' => $movement->unit,
            'note' => $movement->note,
            'period_year' => $movement->period_year,
            'period_month' => $movement->period_month,
            'period_label' => $this->fabaRecapService->formatPeriodLabel($movement->period_year, $movement->period_month),
            'approval_status' => $this->fabaRecapService->getPeriodStatus($movement->period_year, $movement->period_month),
            'created_by_user' => $movement->createdByUser,
            'updated_by_user' => $movement->updatedByUser,
            'created_at' => $movement->created_at?->toIso8601String(),
            'updated_at' => $movement->updated_at?->toIso8601String(),
            'allowed_actions' => $this->allowedActions($movement, $user),
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedActions(FabaMovement $movement, $user): array
    {
        $actions = [];

        if ($user->hasPermission('faba_production.view')) {
            $actions[] = 'view';
        }

        if ($this->canAccessMovement($movement, $user, 'faba_production.edit') && ! $this->fabaRecapService->isPeriodLocked($movement->period_year, $movement->period_month)) {
            $actions[] = 'update';
        }

        if ($this->canAccessMovement($movement, $user, 'faba_production.delete') && ! $this->fabaRecapService->isPeriodLocked($movement->period_year, $movement->period_month)) {
            $actions[] = 'delete';
        }

        return $actions;
    }

    protected function resolvePerPage(Request $request): int
    {
        return max(1, min((int) $request->integer('per_page', 15), 100));
    }

    protected function canAccessMovement(FabaMovement $movement, User $user, string $permission): bool
    {
        if (! $user->hasPermission($permission)) {
            return false;
        }

        if ($user->hasRole('operator')) {
            return (string) $movement->created_by === (string) $user->id;
        }

        return true;
    }

    /**
     * @return array<string, int>
     */
    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
