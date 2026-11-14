<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\ApproveWasteRecordRequest;
use App\Http\Requests\WasteManagement\RejectWasteRecordRequest;
use App\Http\Requests\WasteManagement\SubmitWasteRecordRequest;
use App\Http\Requests\WasteManagement\WasteRecordExportRequest;
use App\Http\Requests\WasteManagement\WasteRecordIndexRequest;
use App\Http\Requests\WasteManagement\WasteRecordRequest;
use App\Models\Organization;
use App\Models\User;
use App\Models\WasteRecord;
use App\Models\WasteType;
use App\Services\CsvExportService;
use App\Services\TenantService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WasteRecordsController extends Controller
{
    protected function canEditAllRecords(): bool
    {
        return Auth::user()?->hasPermission('waste_records.edit_all') ?? false;
    }

    protected function canEditOwnRecords(): bool
    {
        return Auth::user()?->hasPermission('waste_records.edit_own') ?? false;
    }

    protected function canViewAllRecords(): bool
    {
        return Auth::user()?->hasPermission('waste_records.view_all') ?? false;
    }

    protected function canApproveRecords(): bool
    {
        return Auth::user()?->hasPermission('waste_records.approve') ?? false;
    }

    protected function canRejectRecords(): bool
    {
        return Auth::user()?->hasPermission('waste_records.reject') ?? false;
    }

    protected function canViewRecord(WasteRecord $wasteRecord): bool
    {
        return $this->canViewAllRecords() || $wasteRecord->created_by === Auth::id();
    }

    protected function canModifyRecord(WasteRecord $wasteRecord): bool
    {
        if ($this->canEditAllRecords()) {
            return true;
        }

        return $this->canEditOwnRecords() && $wasteRecord->created_by === Auth::id();
    }

    /**
     * Display a listing of waste records.
     */
    public function index(WasteRecordIndexRequest $request): Response
    {
        if ($request->validated('expiry') === 'expired') {
            return $this->expiredSnapshot($request);
        }

        $alerts = app(\App\Services\OperationalAlerts::class);
        $snapshot = $alerts->snapshot($request->validated('month'));
        $attention = $request->validated('attention');
        $base = $alerts->records($request->user())->whereDate('date', '<=', $snapshot->toDateString());
        $summary = [
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'pending' => $this->applyRecordAttention(clone $base, 'pending', $snapshot)->count(),
            'expiring_soon' => $this->applyRecordAttention(clone $base, 'expiring_soon', $snapshot)->count(),
            'expired' => $this->applyRecordAttention(clone $base, 'expired', $snapshot)->count(),
        ];
        $paginated = $request->header('X-Waspro-Records-Contract') === '2';
        $query = $this->applyRecordAttention(clone $base, $attention, $snapshot);
        $search = trim((string) $request->validated('search', ''));
        if ($paginated && $search !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], strtolower($search)).'%';
            $query->where(function ($q) use ($pattern): void {
                $q->whereRaw('LOWER(record_number) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(source, \'\')) LIKE ?', [$pattern])
                    ->orWhereHas('wasteType', fn ($type) => $type->whereRaw('LOWER(name) LIKE ?', [$pattern])->orWhereRaw('LOWER(code) LIKE ?', [$pattern])
                        ->orWhereHas('category', fn ($category) => $category->whereRaw('LOWER(name) LIKE ?', [$pattern])));
                if (\App\Models\WasteSource::available()) {
                    $q->orWhereHas('wasteSource', fn ($source) => $source->whereRaw('LOWER(name) LIKE ?', [$pattern]));
                }
            });
        }
        $sort = $request->validated('sort', 'latest');
        match ($paginated ? $sort : 'latest') {
            'oldest' => $query->orderBy('date')->orderBy('created_at')->orderBy('id'),
            'quantity_high' => $query->orderByDesc('quantity')->orderByDesc('date')->orderBy('id'),
            'quantity_low' => $query->orderBy('quantity')->orderByDesc('date')->orderBy('id'),
            default => $query->orderByDesc('date')->orderByDesc('created_at')->orderBy('id'),
        };
        $query->when(\App\Models\WasteSource::available(), fn ($q) => $q->with('wasteSource'))
            ->with(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy',
                'haulings' => fn ($q) => $q->whereDate('hauling_date', '<=', $snapshot->toDateString())]);
        $page = $paginated ? $query->paginate(10)->withQueryString() : null;
        $records = $page?->getCollection() ?? $query->get();
        $allocation = app(\App\Services\HaulingPlans::class)->summaries($records);
        $records = $records->map(fn (WasteRecord $record): array => [
            ...$this->serializeWasteRecord($record, false, $allocation[(string) $record->id]),
            'storage_alert' => $alerts->storageAlert($record, $snapshot),
        ])->values();
        $page?->setCollection($records);
        $wasteTypes = WasteType::with(['category', 'characteristic'])->active()->orderBy('name')->get();

        return Inertia::render('waste-management/records/Index', [
            'wasteRecords' => $page ?? $records,
            'recordSummary' => $summary,
            'filters' => ['month' => $snapshot->format('Y-m'), 'attention' => $attention, 'as_of' => $snapshot->toDateString(), 'search' => $search, 'sort' => $sort],
            'wasteTypes' => $wasteTypes,
            'wasteSources' => \App\Models\WasteSource::options(),
        ]);
    }

    private function applyRecordAttention(\Illuminate\Database\Eloquent\Builder $query, ?string $attention, CarbonImmutable $snapshot): \Illuminate\Database\Eloquent\Builder
    {
        if ($attention === 'pending') {
            return $query->where('status', 'pending_review')->whereDate('date', '>=', $snapshot->startOfMonth()->toDateString());
        }
        if (! in_array($attention, ['expired', 'expiring_soon', 'critical', 'backlog', 'completed', 'fresh'], true)) {
            return $query;
        }
        $query->where('status', 'approved');
        $remaining = "waste_records.quantity - COALESCE((SELECT SUM(wh.quantity) FROM waste_haulings wh WHERE wh.waste_record_id = waste_records.id AND wh.deleted_at IS NULL AND wh.status = 'approved' AND wh.hauling_date <= ?), 0)";
        $query->whereRaw($remaining.($attention === 'completed' ? ' <= 0' : ' > 0'), [$snapshot->toDateString()]);

        return match ($attention) {
            'expired' => $query->whereDate('expiry_date', '<', $snapshot->toDateString()),
            'expiring_soon' => $query->whereBetween('expiry_date', [$snapshot->toDateString(), $snapshot->addDays(7)->toDateString()]),
            'critical' => $query->whereDate('expiry_date', '<=', $snapshot->addDays(7)->toDateString()),
            'fresh' => $query->whereDate('expiry_date', '>', $snapshot->addDays(7)->toDateString()),
            default => $query,
        };
    }

    private function expiredSnapshot(WasteRecordIndexRequest $request): Response
    {
        $organization = $request->attributes->get(\App\Http\Middleware\UseOrganizationContext::ATTRIBUTE);
        abort_unless($organization && $request->user()->canAccessOrganization($organization->id), 403);
        $tenant = app(TenantService::class);
        abort_unless($tenant->schemaExists($organization->schema_name), 503);
        $originalSchema = $tenant->getCurrentSchema();

        try {
            $tenant->switchToSchema($organization->schema_name);
            $date = CarbonImmutable::parse($request->validated('as_of'))->endOfDay();
            $query = WasteRecord::query()->approved()->with(['wasteType', 'haulings' => fn ($query) => $query->approved()->whereDate('hauling_date', '<=', $date->toDateString())])
                ->whereDate('date', '<=', $date->toDateString())
                ->whereDate('expiry_date', '<', $date->toDateString())
                ->when($request->validated('unit'), fn ($query, $unit) => $query->where('unit', $unit))
                ->orderBy('expiry_date');
            if (! $this->canViewAllRecords()) {
                $query->byUser(Auth::id());
            }
            $records = $query->get()->filter(fn (WasteRecord $record): bool => $record->getRemainingQuantity() > 0)
                ->map(fn (WasteRecord $record): array => [
                    'id' => $record->id,
                    'record_number' => $record->record_number,
                    'waste_type' => $record->wasteType?->name,
                    'expiry_date' => $record->expiry_date->toDateString(),
                    'remaining_quantity' => $record->getRemainingQuantity(),
                    'unit' => $record->unit,
                ])->values()->all();

            return Inertia::render('waste-management/records/ExpiredSnapshot', [
                'records' => $records,
                'snapshot' => [
                    'date' => $date->toDateString(),
                    'label' => $date->locale('id')->translatedFormat('j F Y'),
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'unit' => $request->validated('unit'),
                ],
            ]);
        } finally {
            $tenant->switchToSchema($originalSchema ?? 'public');
        }
    }

    /**
     * Show the form for creating a new waste record.
     */
    public function create(): Response
    {
        $wasteTypes = WasteType::with(['category', 'characteristic'])
            ->active()
            ->orderBy('name')
            ->get();

        return Inertia::render('waste-management/records/Create', [
            'wasteTypes' => $wasteTypes,
            'wasteSources' => \App\Models\WasteSource::options(),
        ]);
    }

    /**
     * Store a newly created waste record.
     */
    public function store(WasteRecordRequest $request): RedirectResponse
    {
        $organization = $request->attributes->get(\App\Http\Middleware\UseOrganizationContext::ATTRIBUTE);
        abort_unless($organization instanceof Organization, 409);

        // Generate record number: WR-{ORG_CODE}-{YYYY}-{MM}-{SEQ}
        $prefix = 'WR-'.$organization->code.'-'.now()->format('Y-m');
        $recordNumber = app(\App\Services\DocumentNumberService::class)->next(WasteRecord::class, 'record_number', $prefix);

        $wasteRecord = WasteRecord::create([
            'record_number' => $recordNumber,
            'date' => $request->validated('date'),
            'waste_type_id' => $request->validated('waste_type_id'),
            'quantity' => $request->validated('quantity'),
            'unit' => $request->validated('unit'),
            'source' => $request->validated('source'),
            ...$request->safe()->only(['source_id']),
            'description' => $request->validated('description'),
            'notes' => $request->validated('notes'),
            'status' => 'draft',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return Redirect::route('waste-management.records.show', $wasteRecord)
            ->with('success', 'Catatan limbah berhasil dibuat.');
    }

    /**
     * Display the specified waste record.
     */
    public function show(string $id): Response
    {
        // Manually resolve the model to handle multi-tenancy properly
        $wasteRecord = WasteRecord::findOrFail($id);

        if (! $this->canViewRecord($wasteRecord)) {
            abort(403, 'You can only view your own records.');
        }

        $wasteRecord->load(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy']);
        $wasteRecord->load(['haulings.createdBy', 'haulings.approvedByUser']);
        if (\App\Models\WasteSource::available()) {
            $wasteRecord->load('wasteSource');
        }

        return Inertia::render('waste-management/records/Show', [
            'wasteRecord' => $this->serializeWasteRecord($wasteRecord, true),
            'abilities' => [
                'can_edit' => $wasteRecord->canBeEdited() && $this->canModifyRecord($wasteRecord),
                'can_submit' => $wasteRecord->isDraft()
                    && $wasteRecord->created_by === Auth::id()
                    && (Auth::user()?->hasPermission('waste_records.submit') ?? false),
                'can_return_to_draft' => $wasteRecord->isRejected()
                    && $wasteRecord->created_by === Auth::id()
                    && (Auth::user()?->hasPermission('waste_records.submit') ?? false),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified waste record.
     */
    public function edit(string $id): Response
    {
        // Manually resolve the model to handle multi-tenancy properly
        $wasteRecord = WasteRecord::findOrFail($id);

        // Check if user can edit this record
        if (! $wasteRecord->canBeEdited()) {
            abort(403, 'This record cannot be edited in its current status.');
        }

        // Check if user owns this record (unless they can view all)
        if (! $this->canModifyRecord($wasteRecord)) {
            abort(403, 'You do not have permission to edit this record.');
        }

        $wasteTypes = WasteType::with(['category', 'characteristic'])
            ->active()
            ->orderBy('name')
            ->get();

        return Inertia::render('waste-management/records/Edit', [
            'wasteRecord' => $wasteRecord,
            'wasteTypes' => $wasteTypes,
            'wasteSources' => \App\Models\WasteSource::options(isset($wasteRecord) ? $wasteRecord->source_id : null),
        ]);
    }

    /**
     * Update the specified waste record.
     */
    public function update(WasteRecordRequest $request, string $id): RedirectResponse
    {
        // Manually resolve the model to handle multi-tenancy properly
        $wasteRecord = WasteRecord::findOrFail($id);

        // Check if user can edit this record
        if (! $wasteRecord->canBeEdited()) {
            return Redirect::back()
                ->with('error', 'This record cannot be edited in its current status.');
        }

        // Check if user owns this record (unless they can view all)
        if (! $this->canModifyRecord($wasteRecord)) {
            abort(403, 'You do not have permission to edit this record.');
        }

        $approvedHauledQuantity = $wasteRecord->getApprovedHauledQuantity();
        if ((float) $request->validated('quantity') < $approvedHauledQuantity) {
            return Redirect::back()
                ->with('error', "Jumlah limbah tidak boleh lebih kecil dari total yang sudah diangkut ({$approvedHauledQuantity} {$wasteRecord->unit}).")
                ->withInput();
        }

        $wasteRecord->update([
            'date' => $request->validated('date'),
            'waste_type_id' => $request->validated('waste_type_id'),
            'quantity' => $request->validated('quantity'),
            'unit' => $request->validated('unit'),
            ...$request->safe()->only(['source', 'description', 'source_id']),
            'notes' => $request->validated('notes'),
            'updated_by' => Auth::id(),
        ]);

        return Redirect::route('waste-management.records.show', $wasteRecord)
            ->with('success', 'Waste record updated successfully.');
    }

    /**
     * Remove the specified waste record.
     */
    public function destroy(string $id): RedirectResponse
    {
        // Manually resolve the model to handle multi-tenancy properly
        $wasteRecord = WasteRecord::findOrFail($id);

        // Only allow deleting draft or rejected records
        if (! $wasteRecord->canBeEdited()) {
            return Redirect::back()
                ->with('error', 'Cannot delete a record that has been approved or is pending review.');
        }

        // Check if user owns this record (unless they can view all)
        if (! $this->canModifyRecord($wasteRecord)) {
            abort(403, 'You do not have permission to delete this record.');
        }

        $wasteRecord->delete();

        return Redirect::route('waste-management.records.index')
            ->with('success', 'Waste record deleted successfully.');
    }

    /**
     * Display pending approval records.
     */
    public function pendingApproval(): Response
    {
        if (! $this->canApproveRecords() && ! $this->canRejectRecords()) {
            abort(403, 'You do not have permission to view pending approvals.');
        }

        $wasteRecords = WasteRecord::with(['wasteType.category', 'wasteType.characteristic', 'submittedByUser'])
            ->pendingApproval()
            ->orderBy('submitted_at', 'desc')
            ->get();

        return Inertia::render('waste-management/records/PendingApproval', [
            'wasteRecords' => $wasteRecords,
        ]);
    }

    /**
     * Submit waste record for approval.
     */
    public function submit(SubmitWasteRecordRequest $request, string $id): RedirectResponse
    {
        // Manually resolve the model to handle multi-tenancy properly
        $wasteRecord = WasteRecord::findOrFail($id);

        // Check if user can submit this record
        if (! $wasteRecord->canBeSubmitted()) {
            return Redirect::back()
                ->with('error', 'This record cannot be submitted in its current status.');
        }

        // Check if user owns this record
        if ($wasteRecord->created_by !== Auth::id()) {
            abort(403, 'You can only submit your own records.');
        }

        $success = $wasteRecord->submitForApproval(Auth::id());

        if (! $success) {
            return Redirect::back()
                ->with('error', 'Failed to submit record for approval.');
        }

        return Redirect::route('waste-management.records.show', $wasteRecord)
            ->with('success', 'Waste record submitted for approval.');
    }

    /**
     * Approve the waste record.
     */
    public function approve(ApproveWasteRecordRequest $request, string $id): RedirectResponse
    {
        // Manually resolve the model to handle multi-tenancy properly
        $wasteRecord = WasteRecord::findOrFail($id);

        // Check if user has permission to approve
        if (! $this->canApproveRecords()) {
            abort(403, 'You do not have permission to approve records.');
        }

        $success = $wasteRecord->approve(
            Auth::id(),
            $request->validated('approval_notes')
        );

        if (! $success) {
            return Redirect::back()
                ->with('error', 'Failed to approve this record. It may not be in pending review status.');
        }

        return Redirect::route('waste-management.records.pending-approval')
            ->with('success', 'Waste record approved successfully.');
    }

    /**
     * Reject the waste record.
     */
    public function reject(RejectWasteRecordRequest $request, string $id): RedirectResponse
    {
        // Manually resolve the model to handle multi-tenancy properly
        $wasteRecord = WasteRecord::findOrFail($id);

        // Check if user has permission to reject
        if (! $this->canRejectRecords()) {
            abort(403, 'You do not have permission to reject records.');
        }

        $success = $wasteRecord->reject(
            Auth::id(),
            $request->validated('rejection_reason')
        );

        if (! $success) {
            return Redirect::back()
                ->with('error', 'Failed to reject this record. It may not be in pending review status.');
        }

        return Redirect::route('waste-management.records.pending-approval')
            ->with('success', 'Waste record rejected successfully.');
    }

    /**
     * Return rejected record to draft.
     */
    public function returnToDraft(string $id): RedirectResponse
    {
        // Manually resolve the model to handle multi-tenancy properly
        $wasteRecord = WasteRecord::findOrFail($id);

        // Check if user owns this rejected record
        if ($wasteRecord->created_by !== Auth::id()) {
            abort(403, 'You can only return your own rejected records to draft.');
        }

        if (! $wasteRecord->isRejected()) {
            return Redirect::back()
                ->with('error', 'Only rejected records can be returned to draft.');
        }

        $success = $wasteRecord->returnToDraft();

        if (! $success) {
            return Redirect::back()
                ->with('error', 'Failed to return record to draft.');
        }

        return Redirect::route('waste-management.records.edit', $wasteRecord)
            ->with('success', 'Record returned to draft. You can now edit and resubmit.');
    }

    /**
     * Export waste records to CSV.
     */
    public function exportCsv(WasteRecordExportRequest $request): StreamedResponse
    {
        $alerts = app(\App\Services\OperationalAlerts::class);
        $snapshotExport = $request->validated('month') !== null;
        $storageDate = $snapshotExport
            ? $alerts->snapshot($request->validated('month'))
            : CarbonImmutable::parse($request->validated('storage_date') ?? today()->toDateString())->startOfDay();
        $query = $alerts->records($request->user())
            ->when(\App\Models\WasteSource::available(), fn ($query) => $query->with('wasteSource'))
            ->with(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser', 'createdBy',
                'haulings' => fn ($query) => $query->when($snapshotExport, fn ($query) => $query->whereDate('hauling_date', '<=', $storageDate->toDateString()))])
            ->when($snapshotExport, fn ($query) => $query->whereDate('date', '<=', $storageDate->toDateString()))
            ->orderBy('date', 'desc')->orderBy('created_at', 'desc');

        $search = mb_strtolower(trim($request->validated('search') ?? ''));
        $storageStatus = $request->validated('attention') ?? $request->validated('storage_status') ?? 'all';
        $wasteRecords = $query->get()->filter(function (WasteRecord $record) use ($search, $storageStatus, $storageDate, $alerts, $snapshotExport, $request): bool {
            $fields = [$record->record_number, $record->wasteType?->name, $record->wasteType?->code, $record->wasteType?->category?->name, $record->source_name, $record->source];
            if ($search !== '' && ! collect($fields)->contains(fn (?string $value): bool => str_contains(mb_strtolower($value ?? ''), $search))) {
                return false;
            }

            if ($snapshotExport || $request->validated('attention') !== null) {
                return $alerts->matchesAttention($record, $storageStatus, $storageDate);
            }

            /** Legacy storage_date only sets the expiry reference, not a historical stock snapshot. */
            if ($storageStatus === 'all') {
                return true;
            }
            $status = match (true) {
                $record->getRemainingQuantity() <= 0 => 'completed',
                $record->expiry_date === null => 'na',
                $record->expiry_date->toDateString() < $storageDate->toDateString() => 'expired',
                $record->expiry_date->toDateString() <= $storageDate->addDays(7)->toDateString() => 'expiring_soon',
                default => 'fresh',
            };

            return $status === $storageStatus;
        });

        $wasteRecords = match ($request->validated('sort') ?? 'latest') {
            'oldest' => $wasteRecords->sortBy(fn (WasteRecord $record): string => $record->date->toDateString()),
            'quantity_high' => $wasteRecords->sortByDesc(fn (WasteRecord $record): float => (float) $record->quantity),
            'quantity_low' => $wasteRecords->sortBy(fn (WasteRecord $record): float => (float) $record->quantity),
            default => $wasteRecords,
        };

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="waste-records-'.now()->format('Y-m-d').'.csv"',
        ];

        $callback = function () use ($wasteRecords) {
            $file = fopen('php://output', 'w');

            // CSV Header
            CsvExportService::writeRow($file, [
                'Record Number',
                'Date',
                'Expiry Date',
                'Waste Type',
                'Category',
                'Characteristic',
                'Quantity',
                'Unit',
                'Source',
                'Description',
                'Status',
                'Submitted By',
                'Submitted At',
                'Approved By',
                'Approved At',
                'Created At',
            ]);

            $write = function (array $values) use ($file): void {
                CsvExportService::writeRow($file, $values);
            };

            // CSV Data
            foreach ($wasteRecords as $record) {
                $write([
                    $record->record_number,
                    $record->date->format('Y-m-d'),
                    $record->expiry_date ? $record->expiry_date->format('Y-m-d') : 'N/A',
                    $record->wasteType?->name ?? 'N/A',
                    $record->wasteType?->category->name ?? 'N/A',
                    $record->wasteType?->characteristic->name ?? 'N/A',
                    $record->quantity,
                    $record->unit,
                    $record->source_name ?? $record->source ?? 'N/A',
                    $record->description ?? 'N/A',
                    $record->getStatusLabel(),
                    $record->submittedByUser?->name ?? 'N/A',
                    $record->submitted_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $record->approvedByUser?->name ?? 'N/A',
                    $record->approved_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $record->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeWasteRecord(WasteRecord $record, bool $includeHistory = false, ?array $allocation = null): array
    {
        $payload = [
            'id' => $record->id,
            'record_number' => $record->record_number,
            'date' => $record->date?->format('Y-m-d'),
            'expiry_date' => $record->expiry_date?->format('Y-m-d'),
            'waste_type_id' => $record->waste_type_id,
            'quantity' => (float) $record->quantity,
            'unit' => $record->unit,
            'source' => $record->source,
            'source_id' => $record->source_id,
            'source_name' => $record->source_name,
            'description' => $record->description,
            'notes' => $record->notes,
            'status' => $record->status,
            'rejection_reason' => $record->rejection_reason,
            'submitted_at' => $record->submitted_at?->toIso8601String(),
            'approved_at' => $record->approved_at?->toIso8601String(),
            'approval_notes' => $record->approval_notes,
            'operational_status' => $record->getOperationalStatus(),
            'operational_status_label' => $record->getOperationalStatusLabel(),
            'approved_hauled_quantity' => $record->getApprovedHauledQuantity(),
            'remaining_quantity' => $record->getRemainingQuantity(),
            'has_simulated_hauling' => $record->haulings->contains(fn ($hauling): bool => $hauling->status === 'approved' && str_starts_with((string) $hauling->hauling_number, 'SIMULASI-')),
            ...($allocation ?? ['reserved_quantity' => $record->getReservedHaulingQuantity(), ...app(\App\Services\HaulingPlans::class)->summary($record)]),
            'waste_type' => $record->wasteType ? [
                'name' => $record->wasteType->name,
                'code' => $record->wasteType->code,
                'category' => $record->wasteType->category ? [
                    'name' => $record->wasteType->category->name,
                    'code' => $record->wasteType->category->code,
                ] : null,
                'characteristic' => $record->wasteType->characteristic ? [
                    'name' => $record->wasteType->characteristic->name,
                    'is_hazardous' => (bool) $record->wasteType->characteristic->is_hazardous,
                ] : null,
            ] : null,
            'submitted_by_user' => $record->submittedByUser ? [
                'name' => $record->submittedByUser->name,
                'email' => $record->submittedByUser->email,
            ] : null,
            'approved_by_user' => $record->approvedByUser ? [
                'name' => $record->approvedByUser->name,
            ] : null,
            'created_by' => $record->createdBy ? [
                'name' => $record->createdBy->name,
            ] : null,
        ];

        if ($includeHistory) {
            $payload['hauling_history'] = $record->haulings
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
                    'created_by' => $hauling->createdBy ? [
                        'name' => $hauling->createdBy->name,
                    ] : null,
                    'approved_by' => $hauling->approvedByUser ? [
                        'name' => $hauling->approvedByUser->name,
                    ] : null,
                ])
                ->all();
        }

        return $payload;
    }
}
