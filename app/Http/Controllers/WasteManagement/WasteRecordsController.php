<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\ApproveWasteRecordRequest;
use App\Http\Requests\WasteManagement\RejectWasteRecordRequest;
use App\Http\Requests\WasteManagement\SubmitWasteRecordRequest;
use App\Http\Requests\WasteManagement\WasteRecordRequest;
use App\Models\User;
use App\Models\WasteRecord;
use App\Models\WasteType;
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
    public function index(): Response
    {
        $query = WasteRecord::with(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by user permissions
        if (! $this->canViewAllRecords()) {
            $query->byUser(Auth::id());
        }

        $wasteRecords = $query->get();

        return Inertia::render('waste-management/records/Index', [
            'wasteRecords' => $wasteRecords,
        ]);
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
        ]);
    }

    /**
     * Store a newly created waste record.
     */
    public function store(WasteRecordRequest $request): RedirectResponse
    {
        $organization = Auth::user()->organization;

        // Generate record number: WR-{ORG_CODE}-{YYYY}-{MM}-{SEQ}
        $prefix = 'WR-'.$organization->code.'-'.now()->format('Y-m');
        $seq = WasteRecord::where('record_number', 'like', $prefix.'%')->count() + 1;
        $recordNumber = $prefix.'-'.str_pad($seq, 4, '0', STR_PAD_LEFT);

        $wasteRecord = WasteRecord::create([
            'record_number' => $recordNumber,
            'date' => $request->validated('date'),
            'waste_type_id' => $request->validated('waste_type_id'),
            'quantity' => $request->validated('quantity'),
            'unit' => $request->validated('unit'),
            'source' => $request->validated('source'),
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

        return Inertia::render('waste-management/records/Show', [
            'wasteRecord' => $wasteRecord,
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

        $wasteRecord->update([
            'date' => $request->validated('date'),
            'waste_type_id' => $request->validated('waste_type_id'),
            'quantity' => $request->validated('quantity'),
            'unit' => $request->validated('unit'),
            'source' => $request->validated('source'),
            'description' => $request->validated('description'),
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
        if (! $this->canApproveRecords() && ! $this->canViewAllRecords()) {
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
    public function exportCsv(): StreamedResponse
    {
        $query = WasteRecord::with(['wasteType.category', 'wasteType.characteristic', 'submittedByUser', 'approvedByUser'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by user permissions
        if (! $this->canViewAllRecords()) {
            $query->byUser(Auth::id());
        }

        $wasteRecords = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="waste-records-'.now()->format('Y-m-d').'.csv"',
        ];

        $callback = function () use ($wasteRecords) {
            $file = fopen('php://output', 'w');

            // CSV Header
            fputcsv($file, [
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

            // CSV Data
            foreach ($wasteRecords as $record) {
                fputcsv($file, [
                    $record->record_number,
                    $record->date->format('Y-m-d'),
                    $record->expiry_date ? $record->expiry_date->format('Y-m-d') : 'N/A',
                    $record->waste_type->name ?? 'N/A',
                    $record->waste_type->category->name ?? 'N/A',
                    $record->waste_type->characteristic->name ?? 'N/A',
                    $record->quantity,
                    $record->unit,
                    $record->source ?? 'N/A',
                    $record->description ?? 'N/A',
                    $record->getStatusLabel(),
                    $record->submitted_by_user->name ?? 'N/A',
                    $record->submitted_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $record->approved_by_user->name ?? 'N/A',
                    $record->approved_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $record->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
