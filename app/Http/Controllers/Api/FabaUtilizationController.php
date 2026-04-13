<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\FabaUtilizationMovementRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaMovement;
use App\Models\User;
use App\Services\FabaAuditService;
use App\Services\FabaRecapService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FabaUtilizationController extends ApiController
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
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_utilization.view')) {
            return $this->error('Anda tidak memiliki izin untuk melihat data pemanfaatan FABA.', 'FORBIDDEN', status: 403);
        }

        $query = FabaMovement::query()
            ->whereIn('movement_type', self::MOVEMENT_TYPES)
            ->with([
                'vendor:id,name',
                'internalDestination:id,name',
                'purpose:id,name',
                'createdByUser:id,name',
                'updatedByUser:id,name',
            ])
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
                'server_time' => now()->toIso8601String(),
            ],
        );
    }

    public function store(FabaUtilizationMovementRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_utilization.create')) {
            return $this->error('Anda tidak memiliki izin untuk membuat movement pemanfaatan FABA.', 'FORBIDDEN', status: 403);
        }

        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if ($this->fabaRecapService->isPeriodLocked($year, $month)) {
            return $this->error('Periode bulan ini sedang diajukan atau sudah disetujui, sehingga transaksi tidak bisa ditambah.', 'CONFLICT', status: 409);
        }

        $attachmentPath = null;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                'faba/utilization-attachments',
                config('filesystems.default'),
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
            'note' => $validated['note'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $movement->load([
            'vendor:id,name',
            'internalDestination:id,name',
            'purpose:id,name',
            'createdByUser:id,name',
            'updatedByUser:id,name',
        ]);

        $this->fabaAuditService->log(
            $user->id,
            'create',
            FabaAuditLog::MODULE_UTILIZATION,
            FabaMovement::class,
            $movement->id,
            $year,
            $month,
            'Movement pemanfaatan FABA dibuat.',
            $this->serializeMovement($movement, $user)
        );

        return $this->success($this->serializeMovement($movement, $user), 'Movement pemanfaatan FABA berhasil dibuat.', status: 201);
    }

    public function show(Request $request, string $utilization): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('faba_utilization.view')) {
            return $this->error('Anda tidak memiliki izin untuk melihat movement pemanfaatan FABA.', 'FORBIDDEN', status: 403);
        }

        return $this->success($this->serializeMovement($this->findMovementOrFail($utilization), $user));
    }

    public function update(FabaUtilizationMovementRequest $request, string $utilization): JsonResponse
    {
        $user = $request->user();
        $movement = $this->findMovementOrFail($utilization);

        if (! $this->canAccessMovement($movement, $user, 'faba_utilization.edit')) {
            return $this->error('Anda tidak memiliki izin untuk mengubah movement pemanfaatan FABA ini.', 'FORBIDDEN', status: 403);
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

        $attachmentPath = $movement->attachment_path;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store(
                'faba/utilization-attachments',
                config('filesystems.default'),
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
            'note' => $validated['note'] ?? null,
            'updated_by' => $user->id,
        ]);

        $movement->load([
            'vendor:id,name',
            'internalDestination:id,name',
            'purpose:id,name',
            'createdByUser:id,name',
            'updatedByUser:id,name',
        ]);

        $this->fabaAuditService->log(
            $user->id,
            'update',
            FabaAuditLog::MODULE_UTILIZATION,
            FabaMovement::class,
            $movement->id,
            $year,
            $month,
            'Movement pemanfaatan FABA diperbarui.',
            $this->serializeMovement($movement, $user)
        );

        return $this->success($this->serializeMovement($movement, $user), 'Movement pemanfaatan FABA berhasil diperbarui.');
    }

    public function destroy(Request $request, string $utilization): JsonResponse
    {
        $user = $request->user();
        $movement = $this->findMovementOrFail($utilization);

        if (! $this->canAccessMovement($movement, $user, 'faba_utilization.delete')) {
            return $this->error('Anda tidak memiliki izin untuk menghapus movement pemanfaatan FABA ini.', 'FORBIDDEN', status: 403);
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
            FabaAuditLog::MODULE_UTILIZATION,
            FabaMovement::class,
            $movementId,
            $year,
            $month,
            'Movement pemanfaatan FABA dihapus.',
            $payload
        );

        return $this->success(message: 'Movement pemanfaatan FABA berhasil dihapus.');
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
            ])
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMovement(FabaMovement $movement, $user): array
    {
        return [
            'id' => $movement->id,
            'display_number' => sprintf('FM-UTL-%s-%s', $movement->transaction_date->format('Ymd'), strtoupper(substr(str_replace('-', '', $movement->id), -6))),
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
            'period_year' => $movement->period_year,
            'period_month' => $movement->period_month,
            'approval_status' => $this->fabaRecapService->getPeriodStatus($movement->period_year, $movement->period_month),
            'period_label' => $this->fabaRecapService->formatPeriodLabel($movement->period_year, $movement->period_month),
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

        if ($user->hasPermission('faba_utilization.view')) {
            $actions[] = 'view';
        }

        if ($this->canAccessMovement($movement, $user, 'faba_utilization.edit') && ! $this->fabaRecapService->isPeriodLocked($movement->period_year, $movement->period_month)) {
            $actions[] = 'update';
        }

        if ($this->canAccessMovement($movement, $user, 'faba_utilization.delete') && ! $this->fabaRecapService->isPeriodLocked($movement->period_year, $movement->period_month)) {
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
