<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\ApproveWasteHaulingRequest;
use App\Http\Requests\WasteManagement\RejectWasteHaulingRequest;
use App\Http\Requests\WasteManagement\WasteHaulingRequest;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WasteHaulingController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canListBacklog($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat backlog pengangkutan.', 'FORBIDDEN', status: 403);
        }

        $query = WasteRecord::query()
            ->with([
                'wasteType.category',
                'wasteType.characteristic',
                'haulings' => fn ($query) => $query->orderByDesc('hauling_date')->orderByDesc('created_at'),
            ])
            ->approved()
            ->orderByDesc('date');

        if (! $user->hasPermission('waste_hauling.view_all')) {
            $query->where('created_by', $user->id);
        }

        $records = $query
            ->get()
            ->filter(fn (WasteRecord $record): bool => $record->getRemainingQuantity() > 0)
            ->values()
            ->map(fn (WasteRecord $record): array => $this->serializeBacklogRecord($record, $user))
            ->all();

        return $this->success($records, meta: [
            'count' => count($records),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function history(Request $request, string $wasteRecord): JsonResponse
    {
        $user = $request->user();
        $record = WasteRecord::query()
            ->with(['wasteType.category', 'haulings.createdBy', 'haulings.approvedByUser'])
            ->findOrFail($wasteRecord);

        if (! $this->canViewRecordForHauling($user, $record)) {
            return $this->error('Anda tidak memiliki izin untuk melihat riwayat pengangkutan limbah ini.', 'FORBIDDEN', status: 403);
        }

        return $this->success([
            'record' => $this->serializeBacklogRecord($record, $user),
            'haulings' => $record->haulings
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (WasteHauling $hauling): array => $this->serializeHauling($hauling, $user))
                ->all(),
        ]);
    }

    public function store(WasteHaulingRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('waste_hauling.create') && ! $user->hasPermission('waste_hauling.submit')) {
            return $this->error('Anda tidak memiliki izin untuk membuat pengajuan pengangkutan.', 'FORBIDDEN', status: 403);
        }

        $record = WasteRecord::query()->with('haulings')->findOrFail($request->validated('waste_record_id'));

        if (! $record->isApproved()) {
            return $this->error('Hanya limbah yang sudah disetujui yang dapat diajukan angkut.', 'CONFLICT', status: 409);
        }

        if (! $this->canViewRecordForHauling($user, $record)) {
            return $this->error('Anda tidak memiliki izin untuk mengajukan pengangkutan untuk limbah ini.', 'FORBIDDEN', status: 403);
        }

        $quantity = (float) $request->validated('quantity');
        $availableQuantity = max(0, (float) $record->quantity - $record->getReservedHaulingQuantity());

        if ($quantity > $availableQuantity) {
            $message = "Jumlah angkut melebihi sisa yang bisa diajukan. Tersedia {$availableQuantity} {$record->unit}.";

            return $this->error($message, 'VALIDATION_ERROR', [
                'quantity' => [$message],
            ], 422);
        }

        $hauling = WasteHauling::query()->create([
            'hauling_number' => '',
            'waste_record_id' => $record->id,
            'hauling_date' => $request->validated('hauling_date'),
            'quantity' => $quantity,
            'unit' => $record->unit,
            'notes' => $request->validated('notes'),
            'status' => 'pending_approval',
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return $this->success($this->serializeHauling($hauling, $user), 'Pengajuan pengangkutan berhasil dibuat.', status: 201);
    }

    public function show(Request $request, string $wasteHauling): JsonResponse
    {
        $user = $request->user();
        $hauling = $this->findHaulingOrFail($wasteHauling);

        if (! $this->canAccessHauling($user, $hauling) && ! $user->hasPermission('waste_hauling.approve') && ! $user->hasPermission('waste_hauling.reject')) {
            return $this->error('Anda tidak memiliki izin untuk melihat pengajuan ini.', 'FORBIDDEN', status: 403);
        }

        return $this->success($this->serializeHauling($hauling, $user));
    }

    public function pendingApproval(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('waste_hauling.approve') && ! $user->hasPermission('waste_hauling.reject')) {
            return $this->error('Anda tidak memiliki izin untuk melihat antrean approval pengangkutan.', 'FORBIDDEN', status: 403);
        }

        /** @var LengthAwarePaginator $haulings */
        $haulings = WasteHauling::query()
            ->with(['wasteRecord.wasteType.category', 'createdBy', 'submittedByUser'])
            ->pendingApproval()
            ->orderByDesc('submitted_at')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return $this->success(
            collect($haulings->items())->map(fn (WasteHauling $hauling): array => $this->serializeHauling($hauling, $user))->all(),
            meta: [
                'pagination' => $this->paginationMeta($haulings),
                'server_time' => now()->toIso8601String(),
            ],
        );
    }

    public function approve(ApproveWasteHaulingRequest $request, string $wasteHauling): JsonResponse
    {
        $user = $request->user();
        $hauling = $this->findHaulingOrFail($wasteHauling);

        if (! $user->hasPermission('waste_hauling.approve')) {
            return $this->error('Anda tidak memiliki izin untuk menyetujui pengangkutan.', 'FORBIDDEN', status: 403);
        }

        $hauling->loadMissing('wasteRecord.haulings');
        $record = $hauling->wasteRecord;
        $availableQuantity = max(0, (float) $record->quantity - $record->getReservedHaulingQuantity($hauling->id));

        if ((float) $hauling->quantity > $availableQuantity) {
            return $this->error('Jumlah pengangkutan tidak lagi valid karena sisa limbah telah berubah.', 'CONFLICT', status: 409);
        }

        if (! $hauling->approve($user->id, $request->validated('approval_notes'))) {
            return $this->error('Pengajuan ini tidak dapat disetujui pada status saat ini.', 'CONFLICT', status: 409);
        }

        return $this->success($this->serializeHauling($hauling->fresh([
            'wasteRecord.wasteType.category',
            'createdBy',
            'submittedByUser',
            'approvedByUser',
        ]), $user), 'Pengajuan pengangkutan berhasil disetujui.');
    }

    public function reject(RejectWasteHaulingRequest $request, string $wasteHauling): JsonResponse
    {
        $user = $request->user();
        $hauling = $this->findHaulingOrFail($wasteHauling);

        if (! $user->hasPermission('waste_hauling.reject')) {
            return $this->error('Anda tidak memiliki izin untuk menolak pengangkutan.', 'FORBIDDEN', status: 403);
        }

        if (! $hauling->reject($user->id, $request->validated('rejection_reason'))) {
            return $this->error('Pengajuan ini tidak dapat ditolak pada status saat ini.', 'CONFLICT', status: 409);
        }

        return $this->success($this->serializeHauling($hauling->fresh([
            'wasteRecord.wasteType.category',
            'createdBy',
            'submittedByUser',
            'approvedByUser',
        ]), $user), 'Pengajuan pengangkutan berhasil ditolak.');
    }

    public function cancel(Request $request, string $wasteHauling): JsonResponse
    {
        $user = $request->user();
        $hauling = $this->findHaulingOrFail($wasteHauling);

        if (! $user->hasPermission('waste_hauling.cancel') || ! $this->canAccessHauling($user, $hauling)) {
            return $this->error('Anda tidak memiliki izin untuk membatalkan pengajuan ini.', 'FORBIDDEN', status: 403);
        }

        if (! $hauling->cancel()) {
            return $this->error('Pengajuan ini tidak dapat dibatalkan pada status saat ini.', 'CONFLICT', status: 409);
        }

        return $this->success($this->serializeHauling($hauling->fresh([
            'wasteRecord.wasteType.category',
            'createdBy',
            'submittedByUser',
            'approvedByUser',
        ]), $user), 'Pengajuan pengangkutan berhasil dibatalkan.');
    }

    protected function canListBacklog(User $user): bool
    {
        return $user->hasPermission('waste_hauling.view_all') || $user->hasPermission('waste_hauling.view_own');
    }

    protected function canViewRecordForHauling(User $user, WasteRecord $record): bool
    {
        if ($user->hasPermission('waste_hauling.view_all')) {
            return true;
        }

        return $user->hasPermission('waste_hauling.view_own') && (string) $record->created_by === (string) $user->id;
    }

    protected function canAccessHauling(User $user, WasteHauling $hauling): bool
    {
        if ($user->hasPermission('waste_hauling.view_all')) {
            return true;
        }

        return $user->hasPermission('waste_hauling.view_own') && (string) $hauling->created_by === (string) $user->id;
    }

    protected function serializeBacklogRecord(WasteRecord $record, User $user): array
    {
        return [
            'id' => $record->id,
            'record_number' => $record->record_number,
            'date' => $record->date?->format('Y-m-d'),
            'quantity' => (float) $record->quantity,
            'unit' => $record->unit,
            'source' => $record->source,
            'status' => $record->status,
            'waste_type' => $record->wasteType ? [
                'id' => $record->wasteType->id,
                'name' => $record->wasteType->name,
                'code' => $record->wasteType->code,
                'category' => $record->wasteType->category ? [
                    'id' => $record->wasteType->category->id,
                    'name' => $record->wasteType->category->name,
                    'code' => $record->wasteType->category->code,
                ] : null,
            ] : null,
            'approved_hauled_quantity' => $record->getApprovedHauledQuantity(),
            'remaining_quantity' => $record->getRemainingQuantity(),
            'reserved_quantity' => $record->getReservedHaulingQuantity(),
            'operational_status' => $record->getOperationalStatus(),
            'operational_status_label' => $record->getOperationalStatusLabel(),
            'allowed_actions' => $this->allowedRecordActions($user, $record),
        ];
    }

    protected function serializeHauling(WasteHauling $hauling, User $user): array
    {
        return [
            'id' => $hauling->id,
            'hauling_number' => $hauling->hauling_number,
            'hauling_date' => $hauling->hauling_date?->format('Y-m-d'),
            'quantity' => (float) $hauling->quantity,
            'unit' => $hauling->unit,
            'notes' => $hauling->notes,
            'status' => $hauling->status,
            'status_label' => $hauling->getStatusLabel(),
            'submitted_at' => $hauling->submitted_at?->toIso8601String(),
            'approved_at' => $hauling->approved_at?->toIso8601String(),
            'approval_notes' => $hauling->approval_notes,
            'rejection_reason' => $hauling->rejection_reason,
            'waste_record' => $hauling->wasteRecord ? [
                'id' => $hauling->wasteRecord->id,
                'record_number' => $hauling->wasteRecord->record_number,
                'remaining_quantity' => $hauling->wasteRecord->getRemainingQuantity(),
                'waste_type' => $hauling->wasteRecord->wasteType ? [
                    'name' => $hauling->wasteRecord->wasteType->name,
                ] : null,
            ] : null,
            'created_by' => $hauling->createdBy ? [
                'id' => $hauling->createdBy->id,
                'name' => $hauling->createdBy->name,
            ] : null,
            'submitted_by' => $hauling->submittedByUser ? [
                'id' => $hauling->submittedByUser->id,
                'name' => $hauling->submittedByUser->name,
            ] : null,
            'approved_by' => $hauling->approvedByUser ? [
                'id' => $hauling->approvedByUser->id,
                'name' => $hauling->approvedByUser->name,
            ] : null,
            'allowed_actions' => $this->allowedHaulingActions($user, $hauling),
        ];
    }

    protected function allowedRecordActions(User $user, WasteRecord $record): array
    {
        $actions = [];

        if ($this->canViewRecordForHauling($user, $record)) {
            $actions[] = 'view';
        }

        if ($user->hasPermission('waste_hauling.create') && $record->isApproved() && $record->getRemainingQuantity() > 0) {
            $actions[] = 'create_hauling';
        }

        return $actions;
    }

    protected function allowedHaulingActions(User $user, WasteHauling $hauling): array
    {
        $actions = [];

        if ($this->canAccessHauling($user, $hauling)) {
            $actions[] = 'view';
        }

        if ($user->hasPermission('waste_hauling.approve') && $hauling->canBeApproved()) {
            $actions[] = 'approve';
        }

        if ($user->hasPermission('waste_hauling.reject') && $hauling->canBeRejected()) {
            $actions[] = 'reject';
        }

        if ($user->hasPermission('waste_hauling.cancel') && $this->canAccessHauling($user, $hauling) && $hauling->canBeCancelled()) {
            $actions[] = 'cancel';
        }

        return $actions;
    }

    protected function findHaulingOrFail(string $id): WasteHauling
    {
        return WasteHauling::query()
            ->with(['wasteRecord.wasteType.category', 'createdBy', 'submittedByUser', 'approvedByUser'])
            ->findOrFail($id);
    }

    protected function resolvePerPage(Request $request): int
    {
        return max(1, min((int) $request->integer('per_page', 15), 100));
    }

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
