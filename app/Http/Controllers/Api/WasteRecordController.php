<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\ApproveWasteRecordRequest;
use App\Http\Requests\WasteManagement\RejectWasteRecordRequest;
use App\Http\Requests\WasteManagement\SubmitWasteRecordRequest;
use App\Http\Requests\WasteManagement\WasteRecordRequest;
use App\Models\User;
use App\Models\WasteRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WasteRecordController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        if (! $this->canListRecords($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat data waste record.', 'FORBIDDEN', status: 403);
        }

        $query = WasteRecord::query()
            ->with(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy', 'haulings'])
            ->orderByDesc('date')
            ->orderByDesc('created_at');

        if (! $this->canViewAllRecords($user)) {
            $query->byUser($user->id);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('record_number', 'like', '%'.$search.'%')
                    ->orWhere('source', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        /** @var LengthAwarePaginator $records */
        $records = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return $this->success(
            collect($records->items())->map(fn (WasteRecord $record): array => $this->serializeWasteRecord($record, $user))->all(),
            meta: [
                'pagination' => $this->paginationMeta($records),
                'filters' => [
                    'status' => $status ?: null,
                    'search' => $search !== '' ? $search : null,
                ],
                'server_time' => now()->toIso8601String(),
            ],
        );
    }

    public function store(WasteRecordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('waste_records.create')) {
            return $this->error('Anda tidak memiliki izin untuk membuat waste record.', 'FORBIDDEN', status: 403);
        }

        $organization = $user->organization;
        $prefix = 'WR-'.($organization?->code ?? 'ORG').'-'.now()->format('Y-m');
        $sequence = WasteRecord::query()->where('record_number', 'like', $prefix.'%')->count() + 1;

        $record = WasteRecord::query()->create([
            'record_number' => $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'date' => $request->validated('date'),
            'waste_type_id' => $request->validated('waste_type_id'),
            'quantity' => $request->validated('quantity'),
            'unit' => $request->validated('unit'),
            'source' => $request->validated('source'),
            'description' => $request->validated('description'),
            'notes' => $request->validated('notes'),
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $record->load(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy']);

        return $this->success(
            $this->serializeWasteRecord($record, $user),
            'Waste record berhasil dibuat.',
            status: 201,
        );
    }

    public function show(Request $request, string $wasteRecord): JsonResponse
    {
        $user = $request->user();
        $record = $this->findWasteRecordOrFail($wasteRecord);

        if (! $this->canAccessRecord($user, $record)) {
            return $this->error('Anda tidak memiliki izin untuk melihat waste record ini.', 'FORBIDDEN', status: 403);
        }

        return $this->success($this->serializeWasteRecord($record, $user));
    }

    public function update(WasteRecordRequest $request, string $wasteRecord): JsonResponse
    {
        $user = $request->user();
        $record = $this->findWasteRecordOrFail($wasteRecord);

        if (! $this->canEditRecord($user, $record)) {
            return $this->error('Anda tidak memiliki izin untuk mengubah waste record ini.', 'FORBIDDEN', status: 403);
        }

        if (! $record->canBeEdited()) {
            return $this->error('Waste record ini tidak dapat diubah pada status saat ini.', 'CONFLICT', status: 409);
        }

        $approvedHauledQuantity = $record->getApprovedHauledQuantity();
        if ((float) $request->validated('quantity') < $approvedHauledQuantity) {
            $message = "Jumlah limbah tidak boleh lebih kecil dari total yang sudah diangkut ({$approvedHauledQuantity} {$record->unit}).";

            return $this->error($message, 'VALIDATION_ERROR', [
                'quantity' => [$message],
            ], 422);
        }

        $record->update([
            'date' => $request->validated('date'),
            'waste_type_id' => $request->validated('waste_type_id'),
            'quantity' => $request->validated('quantity'),
            'unit' => $request->validated('unit'),
            'source' => $request->validated('source'),
            'description' => $request->validated('description'),
            'notes' => $request->validated('notes'),
            'updated_by' => $user->id,
        ]);

        $record->load(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy']);

        return $this->success($this->serializeWasteRecord($record, $user), 'Waste record berhasil diperbarui.');
    }

    public function destroy(Request $request, string $wasteRecord): JsonResponse
    {
        $user = $request->user();
        $record = $this->findWasteRecordOrFail($wasteRecord);

        if (! $user->hasPermission('waste_records.delete') || ! $this->canAccessRecord($user, $record)) {
            return $this->error('Anda tidak memiliki izin untuk menghapus waste record ini.', 'FORBIDDEN', status: 403);
        }

        if (! $record->canBeEdited()) {
            return $this->error('Waste record ini tidak dapat dihapus pada status saat ini.', 'CONFLICT', status: 409);
        }

        $record->delete();

        return $this->success(message: 'Waste record berhasil dihapus.');
    }

    public function pendingApproval(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('waste_records.approve') && ! $user->hasPermission('waste_records.reject')) {
            return $this->error('Anda tidak memiliki izin untuk melihat antrean approval.', 'FORBIDDEN', status: 403);
        }

        $records = WasteRecord::query()
            ->with(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy'])
            ->pendingApproval()
            ->orderByDesc('submitted_at')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return $this->success(
            collect($records->items())->map(fn (WasteRecord $record): array => $this->serializeWasteRecord($record, $user))->all(),
            meta: [
                'pagination' => $this->paginationMeta($records),
                'server_time' => now()->toIso8601String(),
            ],
        );
    }

    public function submit(SubmitWasteRecordRequest $request, string $wasteRecord): JsonResponse
    {
        $user = $request->user();
        $record = $this->findWasteRecordOrFail($wasteRecord);

        if (! $user->hasPermission('waste_records.submit') || (string) $record->created_by !== (string) $user->id) {
            return $this->error('Anda tidak memiliki izin untuk submit waste record ini.', 'FORBIDDEN', status: 403);
        }

        if (! $record->canBeSubmitted()) {
            return $this->error('Waste record ini tidak dapat disubmit pada status saat ini.', 'CONFLICT', status: 409);
        }

        $record->submitForApproval($user->id);
        $record->load(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy']);

        return $this->success($this->serializeWasteRecord($record, $user), 'Waste record berhasil disubmit untuk approval.');
    }

    public function approve(ApproveWasteRecordRequest $request, string $wasteRecord): JsonResponse
    {
        $user = $request->user();
        $record = $this->findWasteRecordOrFail($wasteRecord);

        if (! $user->hasPermission('waste_records.approve')) {
            return $this->error('Anda tidak memiliki izin untuk approve waste record.', 'FORBIDDEN', status: 403);
        }

        if (! $record->approve($user->id, $request->validated('approval_notes'))) {
            return $this->error('Waste record ini tidak dapat di-approve pada status saat ini.', 'CONFLICT', status: 409);
        }

        $record->load(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy']);

        return $this->success($this->serializeWasteRecord($record, $user), 'Waste record berhasil di-approve.');
    }

    public function reject(RejectWasteRecordRequest $request, string $wasteRecord): JsonResponse
    {
        $user = $request->user();
        $record = $this->findWasteRecordOrFail($wasteRecord);

        if (! $user->hasPermission('waste_records.reject')) {
            return $this->error('Anda tidak memiliki izin untuk reject waste record.', 'FORBIDDEN', status: 403);
        }

        if (! $record->reject($user->id, $request->validated('rejection_reason'))) {
            return $this->error('Waste record ini tidak dapat di-reject pada status saat ini.', 'CONFLICT', status: 409);
        }

        $record->load(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy']);

        return $this->success($this->serializeWasteRecord($record, $user), 'Waste record berhasil di-reject.');
    }

    public function returnToDraft(Request $request, string $wasteRecord): JsonResponse
    {
        $user = $request->user();
        $record = $this->findWasteRecordOrFail($wasteRecord);

        if (! $user->hasPermission('waste_records.submit') || (string) $record->created_by !== (string) $user->id) {
            return $this->error('Anda tidak memiliki izin untuk mengembalikan waste record ini ke draft.', 'FORBIDDEN', status: 403);
        }

        if (! $record->returnToDraft()) {
            return $this->error('Hanya waste record berstatus rejected yang dapat dikembalikan ke draft.', 'CONFLICT', status: 409);
        }

        $record->load(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy']);

        return $this->success($this->serializeWasteRecord($record, $user), 'Waste record berhasil dikembalikan ke draft.');
    }

    protected function canListRecords(User $user): bool
    {
        return $user->hasPermission('waste_records.view_all') || $user->hasPermission('waste_records.view_own');
    }

    protected function canViewAllRecords(User $user): bool
    {
        return $user->hasPermission('waste_records.view_all');
    }

    protected function canAccessRecord(User $user, WasteRecord $record): bool
    {
        if ($this->canViewAllRecords($user)) {
            return true;
        }

        return $user->hasPermission('waste_records.view_own') && (string) $record->created_by === (string) $user->id;
    }

    protected function canEditRecord(User $user, WasteRecord $record): bool
    {
        if ($user->hasPermission('waste_records.edit_all')) {
            return true;
        }

        return $user->hasPermission('waste_records.edit_own') && (string) $record->created_by === (string) $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeWasteRecord(WasteRecord $record, User $user): array
    {
        return [
            'id' => $record->id,
            'record_number' => $record->record_number,
            'date' => $record->date?->format('Y-m-d'),
            'status' => $record->status,
            'status_label' => $record->getStatusLabel(),
            'quantity' => (float) $record->quantity,
            'unit' => $record->unit,
            'source' => $record->source,
            'description' => $record->description,
            'notes' => $record->notes,
            'rejection_reason' => $record->rejection_reason,
            'approval_notes' => $record->approval_notes,
            'submitted_at' => $record->submitted_at?->toIso8601String(),
            'approved_at' => $record->approved_at?->toIso8601String(),
            'expiry_date' => $record->expiry_date?->format('Y-m-d'),
            'expiry_status' => $record->getExpiryStatus(),
            'approved_hauled_quantity' => $record->getApprovedHauledQuantity(),
            'remaining_quantity' => $record->getRemainingQuantity(),
            'reserved_quantity' => $record->getReservedHaulingQuantity(),
            'operational_status' => $record->getOperationalStatus(),
            'operational_status_label' => $record->getOperationalStatusLabel(),
            'waste_type' => $record->wasteType ? [
                'id' => $record->wasteType->id,
                'name' => $record->wasteType->name,
                'code' => $record->wasteType->code,
                'category' => $record->wasteType->category ? [
                    'id' => $record->wasteType->category->id,
                    'name' => $record->wasteType->category->name,
                    'code' => $record->wasteType->category->code,
                ] : null,
                'characteristic' => $record->wasteType->characteristic ? [
                    'id' => $record->wasteType->characteristic->id,
                    'name' => $record->wasteType->characteristic->name,
                    'code' => $record->wasteType->characteristic->code,
                ] : null,
            ] : null,
            'created_by' => $record->createdBy ? [
                'id' => $record->createdBy->id,
                'name' => $record->createdBy->name,
            ] : null,
            'submitted_by' => $record->submittedByUser ? [
                'id' => $record->submittedByUser->id,
                'name' => $record->submittedByUser->name,
            ] : null,
            'approved_by' => $record->approvedByUser ? [
                'id' => $record->approvedByUser->id,
                'name' => $record->approvedByUser->name,
            ] : null,
            'hauling_history' => $record->relationLoaded('haulings')
                ? $record->haulings
                    ->sortBy('created_at')
                    ->values()
                    ->map(fn ($hauling): array => [
                        'id' => $hauling->id,
                        'hauling_number' => $hauling->hauling_number,
                        'hauling_date' => $hauling->hauling_date?->format('Y-m-d'),
                        'quantity' => (float) $hauling->quantity,
                        'unit' => $hauling->unit,
                        'status' => $hauling->status,
                        'status_label' => $hauling->getStatusLabel(),
                        'notes' => $hauling->notes,
                        'submitted_at' => $hauling->submitted_at?->toIso8601String(),
                        'approved_at' => $hauling->approved_at?->toIso8601String(),
                        'approval_notes' => $hauling->approval_notes,
                        'rejection_reason' => $hauling->rejection_reason,
                    ])
                    ->all()
                : [],
            'allowed_actions' => $this->allowedActions($user, $record),
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedActions(User $user, WasteRecord $record): array
    {
        $actions = [];

        if ($this->canAccessRecord($user, $record)) {
            $actions[] = 'view';
        }

        if ($this->canEditRecord($user, $record) && $record->canBeEdited()) {
            $actions[] = 'update';
        }

        if ($user->hasPermission('waste_records.delete') && $this->canAccessRecord($user, $record) && $record->canBeEdited()) {
            $actions[] = 'delete';
        }

        if ($user->hasPermission('waste_records.submit') && (string) $record->created_by === (string) $user->id && $record->canBeSubmitted()) {
            $actions[] = 'submit';
        }

        if ($user->hasPermission('waste_records.submit') && (string) $record->created_by === (string) $user->id && $record->isRejected()) {
            $actions[] = 'return_to_draft';
        }

        if ($user->hasPermission('waste_records.approve') && $record->canBeApproved()) {
            $actions[] = 'approve';
        }

        if ($user->hasPermission('waste_records.reject') && $record->canBeRejected()) {
            $actions[] = 'reject';
        }

        return $actions;
    }

    protected function findWasteRecordOrFail(string $id): WasteRecord
    {
        return WasteRecord::query()
            ->with(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy', 'haulings'])
            ->findOrFail($id);
    }

    protected function resolvePerPage(Request $request): int
    {
        return max(1, min((int) $request->integer('per_page', 15), 100));
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
