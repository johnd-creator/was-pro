<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\TransportationRequest;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WasteTransportationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        if (! $this->canListTransportations($user)) {
            return $this->error('Anda tidak memiliki izin untuk melihat data transportation.', 'FORBIDDEN', status: 403);
        }

        $query = WasteTransportation::query()
            ->with([
                'wasteRecord.wasteType.category',
                'wasteRecord.wasteType.characteristic',
                'vendor',
                'createdBy',
            ])
            ->orderByDesc('transportation_date')
            ->orderByDesc('created_at');

        if (! $this->canViewAllTransportations($user)) {
            $query->where('created_by', $user->id);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('transportation_number', 'like', '%'.$search.'%')
                    ->orWhere('vehicle_number', 'like', '%'.$search.'%')
                    ->orWhere('driver_name', 'like', '%'.$search.'%');
            });
        }

        /** @var LengthAwarePaginator $transportations */
        $transportations = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return $this->success(
            collect($transportations->items())->map(fn (WasteTransportation $transportation): array => $this->serializeTransportation($transportation, $user))->all(),
            meta: [
                'pagination' => $this->paginationMeta($transportations),
                'filters' => [
                    'status' => $status !== '' ? $status : null,
                    'search' => $search !== '' ? $search : null,
                ],
                'server_time' => now()->toIso8601String(),
            ],
        );
    }

    public function options(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('transportation.create')) {
            return $this->error('Anda tidak memiliki izin untuk membuat transportation.', 'FORBIDDEN', status: 403);
        }

        $availableWasteRecords = WasteRecord::query()
            ->with(['wasteType.category'])
            ->withSum([
                'transportations as transported_quantity' => function ($query): void {
                    $query->where('status', '!=', 'cancelled');
                },
            ], 'quantity')
            ->approved()
            ->orderByDesc('date')
            ->get()
            ->filter(fn (WasteRecord $record): bool => (float) ($record->transported_quantity ?? 0) < (float) $record->quantity)
            ->values()
            ->map(function (WasteRecord $record): array {
                $transportedQuantity = (float) ($record->transported_quantity ?? 0);
                $remainingQuantity = max(0, (float) $record->quantity - $transportedQuantity);

                return [
                    'id' => $record->id,
                    'record_number' => $record->record_number,
                    'date' => $record->date?->format('Y-m-d'),
                    'quantity' => (float) $record->quantity,
                    'unit' => $record->unit,
                    'transported_quantity' => $transportedQuantity,
                    'remaining_quantity' => $remainingQuantity,
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
                ];
            })
            ->all();

        return $this->success([
            'waste_records' => $availableWasteRecords,
            'vendors' => Vendor::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'license_expiry_date']),
        ], meta: [
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function store(TransportationRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermission('transportation.create')) {
            return $this->error('Anda tidak memiliki izin untuk membuat transportation.', 'FORBIDDEN', status: 403);
        }

        $wasteRecord = WasteRecord::query()->findOrFail($request->validated('waste_record_id'));

        $quantityError = $this->validateQuantityLimit(
            $wasteRecord,
            (float) $request->validated('quantity'),
        );

        if ($quantityError !== null) {
            return $this->error(
                $quantityError,
                'VALIDATION_ERROR',
                ['quantity' => [$quantityError]],
                422,
            );
        }

        $transportation = WasteTransportation::query()->create([
            'transportation_number' => '',
            'waste_record_id' => $wasteRecord->id,
            'vendor_id' => $request->validated('vendor_id'),
            'transportation_date' => $request->validated('transportation_date'),
            'quantity' => $request->validated('quantity'),
            'unit' => $wasteRecord->unit,
            'vehicle_number' => $request->validated('vehicle_number'),
            'driver_name' => $request->validated('driver_name'),
            'driver_phone' => $request->validated('driver_phone'),
            'notes' => $request->validated('notes'),
            'status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $transportation->load([
            'wasteRecord.wasteType.category',
            'wasteRecord.wasteType.characteristic',
            'vendor',
            'createdBy',
        ]);

        return $this->success(
            $this->serializeTransportation($transportation, $user),
            'Waste transportation berhasil dibuat.',
            status: 201,
        );
    }

    public function show(Request $request, string $wasteTransportation): JsonResponse
    {
        $user = $request->user();
        $transportation = $this->findTransportationOrFail($wasteTransportation);

        if (! $this->canAccessTransportation($user, $transportation)) {
            return $this->error('Anda tidak memiliki izin untuk melihat transportation ini.', 'FORBIDDEN', status: 403);
        }

        return $this->success($this->serializeTransportation($transportation, $user));
    }

    public function update(TransportationRequest $request, string $wasteTransportation): JsonResponse
    {
        $user = $request->user();
        $transportation = $this->findTransportationOrFail($wasteTransportation);

        if (! $user->hasPermission('transportation.edit') || ! $this->canAccessTransportation($user, $transportation)) {
            return $this->error('Anda tidak memiliki izin untuk mengubah transportation ini.', 'FORBIDDEN', status: 403);
        }

        if (! $transportation->canBeCancelled()) {
            return $this->error('Transportation ini tidak dapat diubah pada status saat ini.', 'CONFLICT', status: 409);
        }

        $wasteRecord = WasteRecord::query()->findOrFail($request->validated('waste_record_id'));
        $quantityError = $this->validateQuantityLimit(
            $wasteRecord,
            (float) $request->validated('quantity'),
            $transportation->id,
        );

        if ($quantityError !== null) {
            return $this->error(
                $quantityError,
                'VALIDATION_ERROR',
                ['quantity' => [$quantityError]],
                422,
            );
        }

        $transportation->update([
            'waste_record_id' => $wasteRecord->id,
            'vendor_id' => $request->validated('vendor_id'),
            'transportation_date' => $request->validated('transportation_date'),
            'quantity' => $request->validated('quantity'),
            'unit' => $wasteRecord->unit,
            'vehicle_number' => $request->validated('vehicle_number'),
            'driver_name' => $request->validated('driver_name'),
            'driver_phone' => $request->validated('driver_phone'),
            'notes' => $request->validated('notes'),
            'updated_by' => $user->id,
        ]);

        $transportation->load([
            'wasteRecord.wasteType.category',
            'wasteRecord.wasteType.characteristic',
            'vendor',
            'createdBy',
        ]);

        return $this->success($this->serializeTransportation($transportation, $user), 'Waste transportation berhasil diperbarui.');
    }

    public function destroy(Request $request, string $wasteTransportation): JsonResponse
    {
        $user = $request->user();
        $transportation = $this->findTransportationOrFail($wasteTransportation);

        if (! $user->hasPermission('transportation.delete') || ! $this->canAccessTransportation($user, $transportation)) {
            return $this->error('Anda tidak memiliki izin untuk menghapus transportation ini.', 'FORBIDDEN', status: 403);
        }

        if (! $transportation->canBeCancelled()) {
            return $this->error('Transportation ini tidak dapat dihapus pada status saat ini.', 'CONFLICT', status: 409);
        }

        $transportation->delete();

        return $this->success(message: 'Waste transportation berhasil dihapus.');
    }

    public function dispatch(Request $request, string $wasteTransportation): JsonResponse
    {
        $user = $request->user();
        $transportation = $this->findTransportationOrFail($wasteTransportation);

        if (! $user->hasPermission('transportation.dispatch') || ! $this->canAccessTransportation($user, $transportation)) {
            return $this->error('Anda tidak memiliki izin untuk dispatch transportation ini.', 'FORBIDDEN', status: 403);
        }

        if (! $transportation->dispatch()) {
            return $this->error('Transportation ini tidak dapat di-dispatch pada status saat ini.', 'CONFLICT', status: 409);
        }

        $transportation->load([
            'wasteRecord.wasteType.category',
            'wasteRecord.wasteType.characteristic',
            'vendor',
            'createdBy',
        ]);

        return $this->success($this->serializeTransportation($transportation, $user), 'Waste transportation berhasil di-dispatch.');
    }

    public function deliver(Request $request, string $wasteTransportation): JsonResponse
    {
        $user = $request->user();
        $transportation = $this->findTransportationOrFail($wasteTransportation);

        if (! $user->hasPermission('transportation.deliver') || ! $this->canAccessTransportation($user, $transportation)) {
            return $this->error('Anda tidak memiliki izin untuk menyelesaikan transportation ini.', 'FORBIDDEN', status: 403);
        }

        $validated = $request->validate([
            'delivery_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $transportation->markAsDelivered($validated['delivery_notes'] ?? null)) {
            return $this->error('Transportation ini tidak dapat ditandai delivered pada status saat ini.', 'CONFLICT', status: 409);
        }

        $transportation->load([
            'wasteRecord.wasteType.category',
            'wasteRecord.wasteType.characteristic',
            'vendor',
            'createdBy',
        ]);

        return $this->success($this->serializeTransportation($transportation, $user), 'Waste transportation berhasil ditandai delivered.');
    }

    public function cancel(Request $request, string $wasteTransportation): JsonResponse
    {
        $user = $request->user();
        $transportation = $this->findTransportationOrFail($wasteTransportation);

        if (! $user->hasPermission('transportation.cancel') || ! $this->canAccessTransportation($user, $transportation)) {
            return $this->error('Anda tidak memiliki izin untuk membatalkan transportation ini.', 'FORBIDDEN', status: 403);
        }

        if (! $transportation->cancel()) {
            return $this->error('Transportation ini tidak dapat dibatalkan pada status saat ini.', 'CONFLICT', status: 409);
        }

        $transportation->load([
            'wasteRecord.wasteType.category',
            'wasteRecord.wasteType.characteristic',
            'vendor',
            'createdBy',
        ]);

        return $this->success($this->serializeTransportation($transportation, $user), 'Waste transportation berhasil dibatalkan.');
    }

    protected function canListTransportations(User $user): bool
    {
        return $user->hasPermission('transportation.view_all') || $user->hasPermission('transportation.view_own');
    }

    protected function canViewAllTransportations(User $user): bool
    {
        return $user->hasPermission('transportation.view_all');
    }

    protected function canAccessTransportation(User $user, WasteTransportation $transportation): bool
    {
        if ($this->canViewAllTransportations($user)) {
            return true;
        }

        return $user->hasPermission('transportation.view_own')
            && (string) $transportation->created_by === (string) $user->id;
    }

    protected function findTransportationOrFail(string $id): WasteTransportation
    {
        return WasteTransportation::query()
            ->with([
                'wasteRecord.wasteType.category',
                'wasteRecord.wasteType.characteristic',
                'vendor',
                'createdBy',
            ])
            ->findOrFail($id);
    }

    protected function validateQuantityLimit(WasteRecord $wasteRecord, float $quantity, ?string $ignoreTransportationId = null): ?string
    {
        $totalTransported = WasteTransportation::query()
            ->where('waste_record_id', $wasteRecord->id)
            ->when($ignoreTransportationId, fn ($query) => $query->where('id', '!=', $ignoreTransportationId))
            ->where('status', '!=', 'cancelled')
            ->sum('quantity');

        $remainingQuantity = (float) $wasteRecord->quantity - (float) $totalTransported;

        if ($quantity > $remainingQuantity) {
            return "Cannot transport {$quantity} {$wasteRecord->unit}. Only {$remainingQuantity} {$wasteRecord->unit} remaining.";
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTransportation(WasteTransportation $transportation, User $user): array
    {
        $remainingQuantity = $transportation->getRemainingQuantity();

        return [
            'id' => $transportation->id,
            'transportation_number' => $transportation->transportation_number,
            'transportation_date' => $transportation->transportation_date?->format('Y-m-d'),
            'quantity' => (float) $transportation->quantity,
            'unit' => $transportation->unit,
            'status' => $transportation->status,
            'status_label' => $transportation->getStatusLabel(),
            'vehicle_number' => $transportation->vehicle_number,
            'driver_name' => $transportation->driver_name,
            'driver_phone' => $transportation->driver_phone,
            'notes' => $transportation->notes,
            'delivery_notes' => $transportation->delivery_notes,
            'dispatched_at' => $transportation->dispatched_at?->toIso8601String(),
            'delivered_at' => $transportation->delivered_at?->toIso8601String(),
            'vendor' => $transportation->vendor ? [
                'id' => $transportation->vendor->id,
                'name' => $transportation->vendor->name,
                'code' => $transportation->vendor->code,
            ] : null,
            'waste_record' => $transportation->wasteRecord ? [
                'id' => $transportation->wasteRecord->id,
                'record_number' => $transportation->wasteRecord->record_number,
                'date' => $transportation->wasteRecord->date?->format('Y-m-d'),
                'quantity' => (float) $transportation->wasteRecord->quantity,
                'unit' => $transportation->wasteRecord->unit,
                'remaining_quantity' => $remainingQuantity,
                'waste_type' => $transportation->wasteRecord->wasteType ? [
                    'id' => $transportation->wasteRecord->wasteType->id,
                    'name' => $transportation->wasteRecord->wasteType->name,
                    'code' => $transportation->wasteRecord->wasteType->code,
                    'category' => $transportation->wasteRecord->wasteType->category ? [
                        'id' => $transportation->wasteRecord->wasteType->category->id,
                        'name' => $transportation->wasteRecord->wasteType->category->name,
                        'code' => $transportation->wasteRecord->wasteType->category->code,
                    ] : null,
                    'characteristic' => $transportation->wasteRecord->wasteType->characteristic ? [
                        'id' => $transportation->wasteRecord->wasteType->characteristic->id,
                        'name' => $transportation->wasteRecord->wasteType->characteristic->name,
                        'code' => $transportation->wasteRecord->wasteType->characteristic->code,
                    ] : null,
                ] : null,
            ] : null,
            'created_by' => $transportation->createdBy ? [
                'id' => $transportation->createdBy->id,
                'name' => $transportation->createdBy->name,
            ] : null,
            'allowed_actions' => $this->allowedActions($user, $transportation),
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedActions(User $user, WasteTransportation $transportation): array
    {
        $actions = [];

        if ($this->canAccessTransportation($user, $transportation)) {
            $actions[] = 'view';
        }

        if ($user->hasPermission('transportation.edit') && $this->canAccessTransportation($user, $transportation) && $transportation->canBeCancelled()) {
            $actions[] = 'update';
        }

        if ($user->hasPermission('transportation.delete') && $this->canAccessTransportation($user, $transportation) && $transportation->canBeCancelled()) {
            $actions[] = 'delete';
        }

        if ($user->hasPermission('transportation.dispatch') && $this->canAccessTransportation($user, $transportation) && $transportation->canBeDispatched()) {
            $actions[] = 'dispatch';
        }

        if ($user->hasPermission('transportation.deliver') && $this->canAccessTransportation($user, $transportation) && $transportation->canBeDelivered()) {
            $actions[] = 'deliver';
        }

        if ($user->hasPermission('transportation.cancel') && $this->canAccessTransportation($user, $transportation) && $transportation->canBeCancelled()) {
            $actions[] = 'cancel';
        }

        return $actions;
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
