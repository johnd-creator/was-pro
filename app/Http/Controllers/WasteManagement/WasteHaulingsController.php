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
        if (! request()->header('X-Inertia') || request()->header('X-Waspro-Backlog-Contract') !== '2') {
            return $this->legacyIndex();
        }
        $plansService = app(\App\Services\HaulingPlans::class);
        $plansEnabled = $plansService->enabled();
        $viewAll = $this->canViewAllHaulings();
        $today = today($plansService->timezone())->toDateString();
        $soon = today($plansService->timezone())->addDays(7)->toDateString();
        $search = is_string(request()->query('search')) ? mb_substr(trim(request()->query('search')), 0, 200) : '';
        $urgency = in_array(request()->query('urgency'), ['expired', 'expiring_soon', 'fresh'], true) ? request()->query('urgency') : 'all';
        $sort = in_array(request()->query('sort'), ['latest', 'remaining_high'], true) ? request()->query('sort') : 'priority';
        $approved = WasteHauling::query()->approved()->selectRaw('waste_record_id, SUM(quantity) AS approved_quantity')->groupBy('waste_record_id');
        $remaining = '(waste_records.quantity - COALESCE(hauling_totals.approved_quantity, 0))';
        $base = WasteRecord::query()->approved()
            ->leftJoinSub($approved, 'hauling_totals', 'hauling_totals.waste_record_id', '=', 'waste_records.id')
            ->whereRaw($remaining.' > 0');
        if (! $viewAll) {
            $base->where(fn ($own) => $own->where('waste_records.created_by', Auth::id())
                ->when($plansEnabled, fn ($shared) => $shared->orWhereIn('waste_records.id', $plansService->operatorPlans()->select('waste_record_id'))));
        }

        $totals = (clone $base)->selectRaw("unit, COUNT(*) AS total_records, SUM($remaining) AS remaining_quantity,
            SUM(CASE WHEN expiry_date < ? THEN 1 ELSE 0 END) AS expired,
            SUM(CASE WHEN expiry_date >= ? AND expiry_date <= ? THEN 1 ELSE 0 END) AS expiring_soon,
            SUM(CASE WHEN expiry_date > ? THEN 1 ELSE 0 END) AS fresh,
            SUM(CASE WHEN expiry_date <= ? THEN $remaining ELSE 0 END) AS attention_quantity", [$today, $today, $soon, $soon, $soon])
            ->groupBy('unit')->orderBy('unit')->get();
        $query = (clone $base)->select('waste_records.*')->selectRaw('COALESCE(hauling_totals.approved_quantity, 0) AS approved_hauled_quantity')
            ->with(['wasteType.category', 'wasteType.characteristic', 'haulings' => fn ($history) => $history
                ->select(['id', 'waste_record_id', 'hauling_number', 'hauling_date', 'quantity', 'unit', 'status', 'created_by'])
                ->when(! $viewAll && ! $this->canApproveHaulings() && ! $this->canRejectHaulings(), fn ($own) => $own->where('created_by', Auth::id()))
                ->orderByDesc('hauling_date')->orderByDesc('created_at')]);
        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(fn ($match) => $match->where('record_number', 'ilike', $like)
                ->orWhereHas('wasteType', fn ($type) => $type->where('name', 'ilike', $like)->orWhere('code', 'ilike', $like)
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'ilike', $like)))
                ->orWhere(fn ($source) => $source->where('source', 'ilike', $like)->when(! $viewAll, fn ($own) => $own->where('waste_records.created_by', Auth::id()))));
        }
        match ($urgency) {
            'expired' => $query->where('expiry_date', '<', $today),
            'expiring_soon' => $query->whereBetween('expiry_date', [$today, $soon]),
            'fresh' => $query->where('expiry_date', '>', $soon),
            default => null,
        };
        match ($sort) {
            'latest' => $query->orderByDesc('date'),
            'remaining_high' => $query->orderBy('unit')->orderByRaw($remaining.' DESC'),
            default => $query->orderByRaw('CASE WHEN expiry_date < ? THEN 0 WHEN expiry_date <= ? THEN 1 WHEN expiry_date IS NOT NULL THEN 2 ELSE 3 END', [$today, $soon])->orderByRaw('COALESCE(expiry_date, date)'),
        };
        $page = $query->orderBy('waste_records.id')->paginate(10)->withQueryString();
        if ($page->currentPage() > $page->lastPage()) {
            $page = $query->paginate(10, ['*'], 'page', $page->lastPage())->withQueryString();
        }
        $summaries = $plansService->summaries($page->getCollection());
        $records = $page->getCollection()->map(fn (WasteRecord $record): array => $this->serializeBacklogRecord($record, $summaries[$record->id], true))->all();
        $plans = $plansEnabled ? \App\Models\WasteHaulingPlan::query()->where('status', 'approved')
            ->whereIn('waste_record_id', $page->getCollection()->modelKeys())
            ->with(['wasteRecord', 'createdBy', 'approvedByUser', 'closedByUser', 'haulings'])
            ->when(! $viewAll, fn ($query) => $query->where(fn ($own) => $own->where('created_by', Auth::id())->orWhereIn('id', $plansService->operatorPlans()->select('id'))))->get() : collect();
        $metadataAvailable = app(\App\Services\HaulingMetadata::class)->available();

        return Inertia::render('waste-management/haulings/Index', [
            'backlogContract' => 2,
            'backlogContracts' => [1, 2],
            'approvedPlans' => $plans->map(fn ($plan) => app(WasteHaulingPlansController::class)->serialize($plan, true)),
            'actualDateLimit' => $plansService->actualDateLimit(),
            'actualDateTimezone' => $plansService->timezone(),
            'plansEnabled' => $plansEnabled,
            'metadataAvailable' => $metadataAvailable,
            'vendors' => $metadataAvailable ? \App\Models\Vendor::query()->active()->orderBy('name')->get(['id', 'name', 'license_number', 'license_expiry_date'])->map(fn ($vendor) => [...$vendor->toArray(), 'license_expiry_date' => $vendor->license_expiry_date?->format('Y-m-d')]) : [],
            'records' => $records,
            'filters' => ['search' => $search, 'urgency' => $urgency, 'sort' => $sort],
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'from' => $page->firstItem(), 'to' => $page->lastItem()],
            'stats' => [
                'total_records' => (int) $totals->sum('total_records'),
                'remaining_quantities' => $totals->map(fn ($row): array => ['unit' => $row->unit, 'quantity' => round((float) $row->remaining_quantity, 2)])->all(),
                'expired' => (int) $totals->sum('expired'),
                'expiring_soon' => (int) $totals->sum('expiring_soon'),
                'fresh' => (int) $totals->sum('fresh'),
                'attention_quantities' => $totals->filter(fn ($row): bool => (int) $row->expired + (int) $row->expiring_soon > 0)->map(fn ($row): array => ['unit' => $row->unit, 'quantity' => round((float) $row->attention_quantity, 2)])->values()->all(),
                'pending_requests' => WasteHauling::query()->pendingApproval()->when(! $viewAll, fn ($query) => $query->where('created_by', Auth::id()))->count(),
            ],
        ]);
    }

    private function legacyIndex(): Response
    {
        $query = WasteRecord::query()
            ->with([
                'wasteType.category',
                'wasteType.characteristic',
                'haulings' => fn ($query) => $query
                    ->with(['vendor', 'createdBy', 'submittedByUser', 'approvedByUser'])
                    ->orderByDesc('hauling_date')
                    ->orderByDesc('created_at'),
            ])
            ->approved()
            ->orderBy('date');

        if (! $this->canViewAllHaulings()) {
            $query->where(fn ($own) => $own->where('created_by', Auth::id())->when(app(\App\Services\HaulingPlans::class)->enabled(), fn ($shared) => $shared->orWhereIn('id', app(\App\Services\HaulingPlans::class)->operatorPlans()->select('waste_record_id'))));
        }

        $backlog = $query->get()->filter(fn (WasteRecord $record): bool => $record->getRemainingQuantity() > 0)->values();
        $summaries = app(\App\Services\HaulingPlans::class)->summaries($backlog);
        $backlog->each(function (WasteRecord $record): void {
            $record->haulings->each(fn (WasteHauling $hauling) => $hauling->setRelation('wasteRecord', $record));
        });
        $visiblePlans = [];
        if (app(\App\Services\HaulingPlans::class)->enabled()) {
            $planIds = $backlog->flatMap(fn (WasteRecord $record) => $record->haulings->pluck('waste_hauling_plan_id'))->filter()->unique()->values();
            $visiblePlans = $this->canViewAllHaulings() || $this->canApproveHaulings() || $this->canRejectHaulings()
                ? $planIds->flip()->all()
                : \App\Models\WasteHaulingPlan::query()->whereIn('id', $planIds)
                    ->where(fn ($own) => $own->when(Auth::user()->hasPermission('waste_hauling.view_own'), fn ($owned) => $owned->where('created_by', Auth::id()), fn ($none) => $none->whereRaw('1 = 0'))
                        ->orWhereIn('id', app(\App\Services\HaulingPlans::class)->operatorPlans()->select('id')))
                    ->pluck('id')->flip()->all();
        }
        $records = $backlog->map(fn (WasteRecord $record): array => $this->serializeBacklogRecord($record, $summaries[$record->id], false, $visiblePlans))->all();

        $metadataAvailable = app(\App\Services\HaulingMetadata::class)->available();

        $plans = app(\App\Services\HaulingPlans::class)->enabled() ? \App\Models\WasteHaulingPlan::query()->with(['wasteRecord', 'createdBy', 'approvedByUser', 'closedByUser', 'haulings'])->where('status', 'approved')->when(! $this->canViewAllHaulings(), fn ($query) => $query->where(fn ($own) => $own->where('created_by', Auth::id())->orWhereIn('id', app(\App\Services\HaulingPlans::class)->operatorPlans()->select('id'))))->get() : collect();

        return Inertia::render('waste-management/haulings/Index', [
            'initialPage' => max(1, filter_var(request()->query('page', 1), FILTER_VALIDATE_INT) ?: 1),
            'filters' => [
                'search' => is_string(request()->query('search')) ? mb_substr(trim(request()->query('search')), 0, 200) : '',
                'urgency' => in_array(request()->query('urgency'), ['expired', 'expiring_soon', 'fresh'], true) ? request()->query('urgency') : 'all',
                'sort' => in_array(request()->query('sort'), ['latest', 'remaining_high'], true) ? request()->query('sort') : 'priority',
            ],
            'backlogContract' => 1,
            'backlogContracts' => [1, 2],
            'approvedPlans' => $plans->map(fn ($plan) => app(WasteHaulingPlansController::class)->serialize($plan, true)),
            'actualDateLimit' => app(\App\Services\HaulingPlans::class)->actualDateLimit(),
            'actualDateTimezone' => app(\App\Services\HaulingPlans::class)->timezone(),
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
                    ->when(! $this->canViewAllHaulings(), fn ($query) => $query->where('created_by', Auth::id()))
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
        abort_unless($this->canViewAllHaulings() || $record->created_by === Auth::id() || app(\App\Services\HaulingPlans::class)->canOperatePlan(request()->query('waste_hauling_plan_id'), $record->id), 403);

        $metadataAvailable = app(\App\Services\HaulingMetadata::class)->available();

        $plans = app(\App\Services\HaulingPlans::class)->enabled() ? \App\Models\WasteHaulingPlan::query()->where('waste_record_id', $record->id)->where('status', 'approved')->when(! $this->canViewAllHaulings(), fn ($query) => $query->where(fn ($own) => $own->where('created_by', Auth::id())->orWhereIn('id', app(\App\Services\HaulingPlans::class)->operatorPlans()->select('id'))))->get() : collect();

        return Inertia::render('waste-management/haulings/Create', [
            'selectedPlanId' => $plans->contains('id', request()->query('waste_hauling_plan_id')) ? request()->query('waste_hauling_plan_id') : null,
            'approvedPlans' => $plans->map(fn ($plan) => app(WasteHaulingPlansController::class)->serialize($plan)),
            'actualDateLimit' => app(\App\Services\HaulingPlans::class)->actualDateLimit(),
            'actualDateTimezone' => app(\App\Services\HaulingPlans::class)->timezone(),
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

        if (! $this->canViewAllHaulings() && $record->created_by !== Auth::id() && ! app(\App\Services\HaulingPlans::class)->canOperatePlan($request->validated('waste_hauling_plan_id'), $record->id)) {
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
            'actualDateLimit' => app(\App\Services\HaulingPlans::class)->actualDateLimit(),
            'actualDateTimezone' => app(\App\Services\HaulingPlans::class)->timezone(),
            'plansEnabled' => app(\App\Services\HaulingPlans::class)->enabled(),
            'hauling' => $this->serializeHauling($hauling),
            'record' => $this->serializeBacklogRecord($hauling->wasteRecord),
            'abilities' => [
                'can_approve' => $this->canApproveHaulings() && $hauling->canBeApproved()
                    && app(\App\Services\ApprovalSeparation::class)->canApprove(Auth::user(), $hauling),
                'can_reject' => $this->canRejectHaulings() && $hauling->canBeRejected()
                    && (! $hauling->waste_hauling_plan_id || app(\App\Services\ApprovalSeparation::class)->canApprove(Auth::user(), $hauling)),
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
            'plansAvailable' => app(\App\Services\HaulingPlans::class)->enabled(),
            'pendingPlans' => collect(app(\App\Services\OperationalAlerts::class)->forRequest(request())['alerts'])->filter(fn (array $alert): bool => str_starts_with($alert['id'], 'hauling_plan_'))->values()->all(),
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

    protected function serializeBacklogRecord(WasteRecord $record, ?array $summary = null, bool $compactHistory = false, ?array $visiblePlans = null): array
    {
        if (! $compactHistory) {
            $record->loadMissing([
                'wasteType.category',
                'wasteType.characteristic',
                'haulings' => fn ($query) => $query
                    ->with(['vendor', 'createdBy', 'approvedByUser'])
                    ->orderByDesc('hauling_date')
                    ->orderByDesc('created_at'),
            ]);
        }
        $summary ??= app(\App\Services\HaulingPlans::class)->summary($record);

        return [
            'id' => $record->id,
            'record_number' => $record->record_number,
            'date' => $record->date?->format('Y-m-d'),
            'expiry_date' => $record->expiry_date?->format('Y-m-d'),
            'quantity' => (float) $record->quantity,
            'unit' => $record->unit,
            'source' => $record->created_by === Auth::id() || $this->canViewAllHaulings() ? $record->source : null,
            'requires_plan' => ! $this->canViewAllHaulings() && $record->created_by !== Auth::id(),
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
            'reserved_quantity' => $summary['reserved_quantity'] ?? $record->getReservedHaulingQuantity(),
            ...$summary,
            ...(! $this->canViewAllHaulings() && $record->created_by !== Auth::id() ? ['available_quantity' => 0] : []),
            'operational_status' => $record->getOperationalStatus(),
            'operational_status_label' => $record->getOperationalStatusLabel(),
            'hauling_history' => $record->haulings->filter(fn ($row) => $this->canViewAllHaulings() || $this->canApproveHaulings() || $this->canRejectHaulings() || $row->created_by === Auth::id())->values()->map(fn (WasteHauling $hauling): array => $compactHistory ? ['id' => $hauling->id, 'hauling_number' => $hauling->hauling_number, 'hauling_date' => $hauling->hauling_date?->format('Y-m-d'), 'quantity' => (float) $hauling->quantity, 'unit' => $hauling->unit, 'status' => $hauling->status, 'status_label' => $hauling->getStatusLabel()] : $this->serializeHauling($hauling, $visiblePlans === null ? null : array_key_exists((string) $hauling->waste_hauling_plan_id, $visiblePlans)))->all(),
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

    protected function serializeHauling(WasteHauling $hauling, ?bool $canViewPlan = null): array
    {
        $hauling->loadMissing(['wasteRecord.wasteType.category', 'createdBy', 'submittedByUser', 'approvedByUser']);

        return [
            'id' => $hauling->id,
            ...app(\App\Services\HaulingMetadata::class)->serialize($hauling),
            'waste_hauling_plan_id' => $hauling->waste_hauling_plan_id,
            'can_view_plan' => $canViewPlan ?? app(\App\Services\HaulingPlans::class)->canViewPlan($hauling),
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
