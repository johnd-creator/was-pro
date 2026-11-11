<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\ApproveWasteHaulingRequest;
use App\Http\Requests\WasteManagement\RejectWasteHaulingRequest;
use App\Http\Requests\WasteManagement\WasteHaulingRequest;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class WasteHaulingsController extends Controller
{
    protected function canViewAllHaulings(): bool
    {
        return Auth::user()?->hasPermission('waste_hauling.view_all') ?? false;
    }

    protected function canApproveHaulings(): bool
    {
        return Auth::user()?->hasPermission('waste_hauling.approve') ?? false;
    }

    protected function canRejectHaulings(): bool
    {
        return Auth::user()?->hasPermission('waste_hauling.reject') ?? false;
    }

    protected function canAccessHauling(WasteHauling $hauling): bool
    {
        return $this->canViewAllHaulings() || $hauling->created_by === Auth::id();
    }

    public function index(): Response
    {
        $query = WasteRecord::query()
            ->with([
                'wasteType.category',
                'wasteType.characteristic',
                'haulings' => fn ($query) => $query
                    ->with(['vendor', 'createdBy', 'approvedByUser'])
                    ->orderByDesc('hauling_date')
                    ->orderByDesc('created_at'),
            ])
            ->approved()
            ->orderBy('date');

        if (! $this->canViewAllHaulings()) {
            $query->where('created_by', Auth::id());
        }

        $records = $query
            ->get()
            ->filter(fn (WasteRecord $record): bool => $record->getRemainingQuantity() > 0)
            ->values()
            ->map(fn (WasteRecord $record): array => $this->serializeBacklogRecord($record))
            ->all();

        $metadataAvailable = app(\App\Services\HaulingMetadata::class)->available();

        $plans = app(\App\Services\HaulingPlans::class)->enabled() ? \App\Models\WasteHaulingPlan::query()->where('status', 'approved')->when(! $this->canViewAllHaulings(), fn ($query) => $query->where('created_by', Auth::id()))->get() : collect();

        return Inertia::render('waste-management/haulings/Index', [
            'approvedPlans' => $plans->map(fn ($plan) => app(WasteHaulingPlansController::class)->serialize($plan)),
            'actualDateLimit' => today()->toDateString(),
            'actualDateTimezone' => config('app.timezone'),
            'plansEnabled' => app(\App\Services\HaulingPlans::class)->enabled(),
            'metadataAvailable' => $metadataAvailable,
            'vendors' => $metadataAvailable ? \App\Models\Vendor::query()->active()->orderBy('name')->get(['id', 'name', 'license_number', 'license_expiry_date'])->map(fn ($vendor) => [...$vendor->toArray(), 'license_expiry_date' => $vendor->license_expiry_date?->format('Y-m-d')]) : [],
            'records' => $records,
            'stats' => [
                'total_records' => count($records),
                'remaining_quantities' => collect($records)
                    ->groupBy('unit')->sortKeys()
                    ->map(fn ($group, string $unit): array => [
                        'unit' => $unit,
                        'quantity' => round((float) $group->sum('remaining_quantity'), 2),
                    ])->values()->all(),
                'pending_requests' => WasteHauling::query()->pendingApproval()
                    ->when(! $this->canViewAllHaulings(), fn ($query) => $query
                        ->whereHas('wasteRecord', fn ($records) => $records->where('created_by', Auth::id())))
                    ->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        $record = WasteRecord::query()
            ->with(['wasteType.category', 'wasteType.characteristic', 'haulings'])
            ->findOrFail((string) request()->query('waste_record'));

        abort_unless($record->isApproved(), 404);
        abort_unless($this->canViewAllHaulings() || $record->created_by === Auth::id(), 403);

        $metadataAvailable = app(\App\Services\HaulingMetadata::class)->available();

        $plans = app(\App\Services\HaulingPlans::class)->enabled() ? \App\Models\WasteHaulingPlan::query()->where('waste_record_id', $record->id)->where('status', 'approved')->when(! $this->canViewAllHaulings(), fn ($query) => $query->where('created_by', Auth::id()))->get() : collect();

        return Inertia::render('waste-management/haulings/Create', [
            'selectedPlanId' => $plans->contains('id', request()->query('waste_hauling_plan_id')) ? request()->query('waste_hauling_plan_id') : null,
            'approvedPlans' => $plans->map(fn ($plan) => app(WasteHaulingPlansController::class)->serialize($plan)),
            'actualDateLimit' => today()->toDateString(),
            'actualDateTimezone' => config('app.timezone'),
            'plansEnabled' => app(\App\Services\HaulingPlans::class)->enabled(),
            'metadataAvailable' => $metadataAvailable,
            'vendors' => $metadataAvailable ? \App\Models\Vendor::query()->active()->orderBy('name')->get(['id', 'name', 'license_number', 'license_expiry_date'])->map(fn ($vendor) => [...$vendor->toArray(), 'license_expiry_date' => $vendor->license_expiry_date?->format('Y-m-d')]) : [],
            'record' => $this->serializeBacklogRecord($record),
        ]);
    }

    public function store(WasteHaulingRequest $request): RedirectResponse
    {
        $record = WasteRecord::query()
            ->with('haulings')
            ->findOrFail($request->validated('waste_record_id'));

        if (! $record->isApproved()) {
            return Redirect::back()->with('error', 'Hanya limbah yang sudah disetujui yang dapat diajukan angkut.');
        }

        if (! $this->canViewAllHaulings() && $record->created_by !== Auth::id()) {
            abort(403);
        }

        $quantity = $this->normalizeQuantity((float) $request->validated('quantity'));
        $availableQuantity = $this->normalizeQuantity(
            max(0, app(\App\Services\HaulingPlans::class)->availableFor($record, $request->validated('waste_hauling_plan_id')))
        );

        if ($this->quantityExceedsAvailable($quantity, $availableQuantity)) {
            return Redirect::back()
                ->with('error', "Jumlah angkut melebihi sisa yang bisa diajukan. Tersedia {$availableQuantity} {$record->unit}.")
                ->withInput();
        }

        $hauling = WasteHauling::query()->create([
            ...app(\App\Services\HaulingMetadata::class)->attributes($request),
            ...($request->validated('waste_hauling_plan_id') ? ['waste_hauling_plan_id' => $request->validated('waste_hauling_plan_id')] : []),
            'hauling_number' => '',
            'waste_record_id' => $record->id,
            'hauling_date' => $request->validated('hauling_date'),
            'quantity' => $quantity,
            'unit' => $record->unit,
            'notes' => $request->validated('notes'),
            'status' => 'pending_approval',
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return Redirect::route('waste-management.haulings.show', $hauling)
            ->with('success', 'Pengajuan pengangkutan berhasil dibuat dan menunggu persetujuan.');
    }

    public function show(string $id): Response
    {
        $hauling = WasteHauling::query()
            ->with([
                'wasteRecord.wasteType.category',
                'wasteRecord.wasteType.characteristic',
                'wasteRecord.haulings' => fn ($query) => $query
                    ->with(['vendor', 'createdBy', 'approvedByUser'])
                    ->orderBy('created_at'),
                'createdBy',
                'submittedByUser',
                'approvedByUser',
            ])
            ->findOrFail($id);

        if (! $this->canAccessHauling($hauling) && ! $this->canApproveHaulings() && ! $this->canRejectHaulings()) {
            abort(403);
        }

        return Inertia::render('waste-management/haulings/Show', [
            'actualDateLimit' => today()->toDateString(),
            'actualDateTimezone' => config('app.timezone'),
            'plansEnabled' => app(\App\Services\HaulingPlans::class)->enabled(),
            'hauling' => $this->serializeHauling($hauling),
            'record' => $this->serializeBacklogRecord($hauling->wasteRecord),
            'abilities' => [
                'can_approve' => $this->canApproveHaulings() && $hauling->canBeApproved(),
                'can_reject' => $this->canRejectHaulings() && $hauling->canBeRejected(),
                'can_cancel' => (Auth::user()?->hasPermission('waste_hauling.cancel') ?? false)
                    && $hauling->created_by === Auth::id()
                    && $hauling->canBeCancelled(),
            ],
        ]);
    }

    public function attachment(string $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $hauling = WasteHauling::query()->findOrFail($id);
        abort_unless($this->canAccessHauling($hauling) || $this->canApproveHaulings() || $this->canRejectHaulings(), 403);

        return app(\App\Services\HaulingMetadata::class)->download($hauling);
    }

    public function pendingApproval(): Response
    {
        abort_unless($this->canApproveHaulings() || $this->canRejectHaulings(), 403);

        $haulings = WasteHauling::query()
            ->with(['vendor', 'wasteRecord.wasteType.category', 'createdBy', 'submittedByUser'])
            ->pendingApproval()
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (WasteHauling $hauling): array => $this->serializeHauling($hauling))
            ->all();

        return Inertia::render('waste-management/haulings/PendingApproval', [
            'haulings' => $haulings,
        ]);
    }

    public function approve(ApproveWasteHaulingRequest $request, string $id): RedirectResponse
    {
        $hauling = WasteHauling::query()->with('wasteRecord.haulings')->findOrFail($id);

        abort_unless($this->canApproveHaulings(), 403);

        $record = $hauling->wasteRecord;
        $requestedQuantity = $this->normalizeQuantity((float) $hauling->quantity);
        $availableQuantity = $this->normalizeQuantity(
            max(0, app(\App\Services\HaulingPlans::class)->availableFor($record, $hauling->waste_hauling_plan_id, $hauling->id))
        );

        if ($this->quantityExceedsAvailable($requestedQuantity, $availableQuantity)) {
            return Redirect::back()->with('error', 'Jumlah pengangkutan tidak lagi valid karena sisa limbah telah berubah.');
        }

        if (! $hauling->approve(Auth::id(), $request->validated('approval_notes'))) {
            return Redirect::back()->with('error', 'Pengajuan ini tidak dapat disetujui pada status saat ini.');
        }

        return Redirect::route('waste-management.haulings.pending-approval')
            ->with('success', 'Pengajuan pengangkutan berhasil disetujui.');
    }

    public function reject(RejectWasteHaulingRequest $request, string $id): RedirectResponse
    {
        $hauling = WasteHauling::query()->findOrFail($id);

        abort_unless($this->canRejectHaulings(), 403);

        if (! $hauling->reject(Auth::id(), $request->validated('rejection_reason'))) {
            return Redirect::back()->with('error', 'Pengajuan ini tidak dapat ditolak pada status saat ini.');
        }

        return Redirect::route('waste-management.haulings.pending-approval')
            ->with('success', 'Pengajuan pengangkutan berhasil ditolak.');
    }

    public function cancel(string $id): RedirectResponse
    {
        $hauling = WasteHauling::query()->findOrFail($id);

        abort_unless(($this->canAccessHauling($hauling) || $this->canViewAllHaulings()), 403);

        if (! $hauling->cancel()) {
            return Redirect::back()->with('error', 'Pengajuan ini tidak dapat dibatalkan pada status saat ini.');
        }

        return Redirect::route('waste-management.haulings.index')
            ->with('success', 'Pengajuan pengangkutan dibatalkan.');
    }

    protected function serializeBacklogRecord(WasteRecord $record): array
    {
        $record->loadMissing([
            'wasteType.category',
            'wasteType.characteristic',
            'haulings' => fn ($query) => $query
                ->with(['vendor', 'createdBy', 'approvedByUser'])
                ->orderByDesc('hauling_date')
                ->orderByDesc('created_at'),
        ]);

        return [
            'id' => $record->id,
            'record_number' => $record->record_number,
            'date' => $record->date?->format('Y-m-d'),
            'expiry_date' => $record->expiry_date?->format('Y-m-d'),
            'quantity' => (float) $record->quantity,
            'unit' => $record->unit,
            'source' => $record->source,
            'status' => $record->status,
            'waste_type' => [
                'name' => $record->wasteType?->name,
                'code' => $record->wasteType?->code,
                'category' => [
                    'name' => $record->wasteType?->category?->name,
                ],
                'characteristic' => [
                    'name' => $record->wasteType?->characteristic?->name,
                ],
            ],
            'approved_hauled_quantity' => $record->getApprovedHauledQuantity(),
            'remaining_quantity' => $record->getRemainingQuantity(),
            'reserved_quantity' => $record->getReservedHaulingQuantity(),
            ...app(\App\Services\HaulingPlans::class)->summary($record),
            'operational_status' => $record->getOperationalStatus(),
            'operational_status_label' => $record->getOperationalStatusLabel(),
            'hauling_history' => $record->haulings->map(fn (WasteHauling $hauling): array => $this->serializeHauling($hauling))->all(),
        ];
    }

    protected function normalizeQuantity(float $quantity): float
    {
        return round($quantity, 2);
    }

    protected function quantityExceedsAvailable(float $quantity, float $availableQuantity): bool
    {
        return $this->normalizeQuantity($quantity - $availableQuantity) > 0;
    }

    protected function serializeHauling(WasteHauling $hauling): array
    {
        $hauling->loadMissing(['wasteRecord.wasteType.category', 'createdBy', 'submittedByUser', 'approvedByUser']);

        return [
            'id' => $hauling->id,
            ...app(\App\Services\HaulingMetadata::class)->serialize($hauling),
            'waste_hauling_plan_id' => $hauling->waste_hauling_plan_id,
            'can_view_plan' => app(\App\Services\HaulingPlans::class)->canViewPlan($hauling),
            'record_kind' => $hauling->waste_hauling_plan_id ? 'planned_actual' : 'legacy_actual',
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
            'waste_record' => [
                'id' => $hauling->wasteRecord?->id,
                'record_number' => $hauling->wasteRecord?->record_number,
                'quantity' => (float) ($hauling->wasteRecord?->quantity ?? 0),
                'unit' => $hauling->wasteRecord?->unit,
                'remaining_quantity' => $hauling->wasteRecord?->getRemainingQuantity(),
                'waste_type' => [
                    'name' => $hauling->wasteRecord?->wasteType?->name,
                    'category' => [
                        'name' => $hauling->wasteRecord?->wasteType?->category?->name,
                    ],
                ],
            ],
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
        ];
    }
}
