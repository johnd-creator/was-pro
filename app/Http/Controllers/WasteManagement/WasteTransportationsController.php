<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\TransportationRequest;
use App\Models\Vendor;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WasteTransportationsController extends Controller
{
    /**
     * Display a listing of waste transportations.
     */
    public function index(): Response
    {
        $query = WasteTransportation::with([
            'wasteRecord.wasteType.category',
            'wasteRecord.wasteType.characteristic',
            'vendor',
            'createdBy',
        ])
            ->orderBy('transportation_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by user permissions if needed
        if (! Auth::user()->can('transportation.view_all')) {
            $query->where('created_by', Auth::id());
        }

        $wasteTransportations = $query->get();

        // Get statistics
        $stats = [
            'pending' => (clone $query)->pending()->count(),
            'in_transit' => (clone $query)->inTransit()->count(),
            'delivered' => (clone $query)->delivered()->count(),
            'cancelled' => (clone $query)->cancelled()->count(),
        ];

        return Inertia::render('waste-management/transportations/Index', [
            'wasteTransportations' => $wasteTransportations,
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new waste transportation.
     */
    public function create(): Response
    {
        $wasteRecordId = request()->query('waste_record');

        // Get available waste records (approved and not fully transported)
        $wasteRecords = WasteRecord::with(['wasteType', 'wasteType.category'])
            ->withSum([
                'transportations as transported_quantity' => function ($query) {
                    $query->where('status', '!=', 'cancelled');
                },
            ], 'quantity')
            ->approved()
            ->get()
            ->filter(function ($record) {
                return (float) ($record->transported_quantity ?? 0) < (float) $record->quantity;
            })
            ->map(function ($record) {
                $transported = (float) ($record->transported_quantity ?? 0);

                return [
                    'id' => $record->id,
                    'record_number' => $record->record_number,
                    'date' => $record->date->format('Y-m-d'),
                    'waste_type' => $record->wasteType->name,
                    'category' => $record->wasteType->category->name ?? 'N/A',
                    'total_quantity' => $record->quantity,
                    'unit' => $record->unit,
                    'transported_quantity' => $transported,
                    'remaining_quantity' => $record->quantity - $transported,
                    'is_expired' => $record->isExpired(),
                    'is_expiring_soon' => $record->isExpiringSoon(),
                ];
            });

        $vendors = Vendor::active()->orderBy('name')->get();

        // Pre-fill data if waste_record_id is provided
        $prefill = null;
        if ($wasteRecordId) {
            $wasteRecord = WasteRecord::with(['wasteType', 'wasteType.category'])->find($wasteRecordId);
            if ($wasteRecord) {
                $transported = WasteTransportation::where('waste_record_id', $wasteRecord->id)
                    ->where('status', '!=', 'cancelled')
                    ->sum('quantity');

                $prefill = [
                    'waste_record_id' => $wasteRecord->id,
                    'record_number' => $wasteRecord->record_number,
                    'waste_type' => $wasteRecord->wasteType->name,
                    'category' => $wasteRecord->wasteType->category->name ?? 'N/A',
                    'unit' => $wasteRecord->unit,
                    'total_quantity' => $wasteRecord->quantity,
                    'transported_quantity' => $transported,
                    'remaining_quantity' => $wasteRecord->quantity - $transported,
                    'is_expired' => $wasteRecord->isExpired(),
                    'is_expiring_soon' => $wasteRecord->isExpiringSoon(),
                    'expiry_date' => $wasteRecord->expiry_date?->format('Y-m-d'),
                ];
            }
        }

        return Inertia::render('waste-management/transportations/Create', [
            'wasteRecords' => $wasteRecords,
            'vendors' => $vendors,
            'prefill' => $prefill,
        ]);
    }

    /**
     * Store a newly created waste transportation.
     */
    public function store(TransportationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Get waste record to validate quantity
        $wasteRecord = WasteRecord::findOrFail($validated['waste_record_id']);

        // Calculate total transported quantity
        $totalTransported = WasteTransportation::where('waste_record_id', $wasteRecord->id)
            ->where('status', '!=', 'cancelled')
            ->sum('quantity');

        $remainingQuantity = $wasteRecord->quantity - $totalTransported;

        // Validate quantity doesn't exceed remaining
        if ($validated['quantity'] > $remainingQuantity) {
            return Redirect::back()
                ->with('error', "Cannot transport {$validated['quantity']} {$wasteRecord->unit}. Only {$remainingQuantity} {$wasteRecord->unit} remaining.")
                ->withInput();
        }

        $wasteTransportation = WasteTransportation::create([
            'transportation_number' => '', // Will be auto-generated
            'waste_record_id' => $validated['waste_record_id'],
            'vendor_id' => $validated['vendor_id'],
            'transportation_date' => $validated['transportation_date'],
            'quantity' => $validated['quantity'],
            'unit' => $wasteRecord->unit,
            'vehicle_number' => $validated['vehicle_number'] ?? null,
            'driver_name' => $validated['driver_name'] ?? null,
            'driver_phone' => $validated['driver_phone'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return Redirect::route('waste-management.transportations.show', $wasteTransportation)
            ->with('success', 'Waste transportation created successfully.');
    }

    /**
     * Display the specified waste transportation.
     */
    public function show(WasteTransportation $wasteTransportation): Response
    {
        $wasteTransportation->load([
            'wasteRecord.wasteType.category',
            'wasteRecord.wasteType.characteristic',
            'vendor',
            'createdBy',
        ]);

        return Inertia::render('waste-management/transportations/Show', [
            'wasteTransportation' => $wasteTransportation,
        ]);
    }

    /**
     * Show the form for editing the specified waste transportation.
     */
    public function edit(WasteTransportation $wasteTransportation): Response
    {
        // Check if user can edit this transportation
        if (! $wasteTransportation->canBeCancelled()) {
            abort(403, 'This transportation cannot be edited in its current status.');
        }

        $wasteTransportation->load(['wasteRecord', 'vendor']);

        $vendors = Vendor::active()->orderBy('name')->get();

        return Inertia::render('waste-management/transportations/Edit', [
            'wasteTransportation' => $wasteTransportation,
            'vendors' => $vendors,
        ]);
    }

    /**
     * Update the specified waste transportation.
     */
    public function update(TransportationRequest $request, WasteTransportation $wasteTransportation): RedirectResponse
    {
        // Check if transportation can still be edited
        if (! $wasteTransportation->canBeCancelled()) {
            return Redirect::back()
                ->with('error', 'This transportation cannot be edited in its current status.');
        }

        $validated = $request->validated();

        // If waste_record_id changed, validate quantity
        if (isset($validated['waste_record_id']) && $validated['waste_record_id'] != $wasteTransportation->waste_record_id) {
            $wasteRecord = WasteRecord::findOrFail($validated['waste_record_id']);

            $totalTransported = WasteTransportation::where('waste_record_id', $wasteRecord->id)
                ->where('id', '!=', $wasteTransportation->id)
                ->where('status', '!=', 'cancelled')
                ->sum('quantity');

            $remainingQuantity = $wasteRecord->quantity - $totalTransported;

            if ($validated['quantity'] > $remainingQuantity) {
                return Redirect::back()
                    ->with('error', "Cannot transport {$validated['quantity']} {$wasteRecord->unit}. Only {$remainingQuantity} {$wasteRecord->unit} remaining.")
                    ->withInput();
            }
        }

        $wasteTransportation->update([
            'waste_record_id' => $validated['waste_record_id'] ?? $wasteTransportation->waste_record_id,
            'vendor_id' => $validated['vendor_id'] ?? $wasteTransportation->vendor_id,
            'transportation_date' => $validated['transportation_date'] ?? $wasteTransportation->transportation_date,
            'quantity' => $validated['quantity'] ?? $wasteTransportation->quantity,
            'vehicle_number' => $validated['vehicle_number'] ?? $wasteTransportation->vehicle_number,
            'driver_name' => $validated['driver_name'] ?? $wasteTransportation->driver_name,
            'driver_phone' => $validated['driver_phone'] ?? $wasteTransportation->driver_phone,
            'notes' => $validated['notes'] ?? $wasteTransportation->notes,
            'updated_by' => Auth::id(),
        ]);

        return Redirect::route('waste-management.transportations.show', $wasteTransportation)
            ->with('success', 'Waste transportation updated successfully.');
    }

    /**
     * Remove the specified waste transportation.
     */
    public function destroy(WasteTransportation $wasteTransportation): RedirectResponse
    {
        // Only allow deleting pending transportations
        if (! $wasteTransportation->canBeCancelled()) {
            return Redirect::back()
                ->with('error', 'Cannot delete a transportation that is in transit or delivered.');
        }

        $wasteTransportation->delete();

        return Redirect::route('waste-management.transportations.index')
            ->with('success', 'Waste transportation deleted successfully.');
    }

    /**
     * Dispatch the transportation.
     */
    public function dispatch(WasteTransportation $wasteTransportation): RedirectResponse
    {
        if (! $wasteTransportation->canBeDispatched()) {
            return Redirect::back()
                ->with('error', 'This transportation cannot be dispatched in its current status.');
        }

        $wasteTransportation->dispatch();

        return Redirect::route('waste-management.transportations.show', $wasteTransportation)
            ->with('success', 'Waste transportation dispatched successfully.');
    }

    /**
     * Mark the transportation as delivered.
     */
    public function deliver(Request $request, WasteTransportation $wasteTransportation): RedirectResponse
    {
        if (! $wasteTransportation->canBeDelivered()) {
            return Redirect::back()
                ->with('error', 'This transportation cannot be marked as delivered in its current status.');
        }

        $validated = $request->validate([
            'delivery_notes' => 'nullable|string|max:1000',
        ]);

        $wasteTransportation->markAsDelivered($validated['delivery_notes'] ?? null);

        return Redirect::route('waste-management.transportations.show', $wasteTransportation)
            ->with('success', 'Waste transportation marked as delivered.');
    }

    /**
     * Cancel the transportation.
     */
    public function cancel(WasteTransportation $wasteTransportation): RedirectResponse
    {
        if (! $wasteTransportation->canBeCancelled()) {
            return Redirect::back()
                ->with('error', 'This transportation cannot be cancelled in its current status.');
        }

        $wasteTransportation->cancel();

        return Redirect::route('waste-management.transportations.show', $wasteTransportation)
            ->with('success', 'Waste transportation cancelled successfully.');
    }

    /**
     * Export waste transportations to CSV.
     */
    public function exportCsv(): StreamedResponse
    {
        $query = WasteTransportation::with([
            'wasteRecord.wasteType.category',
            'wasteRecord.wasteType.characteristic',
            'vendor',
            'createdBy',
        ])
            ->orderBy('transportation_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by user permissions if needed
        if (! Auth::user()->can('transportation.view_all')) {
            $query->where('created_by', Auth::id());
        }

        $transportations = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="waste-transportations-'.now()->format('Y-m-d').'.csv"',
        ];

        $callback = function () use ($transportations) {
            $file = fopen('php://output', 'w');

            // CSV Header
            fputcsv($file, [
                'Transportation Number',
                'Transportation Date',
                'Waste Record Number',
                'Waste Type',
                'Category',
                'Quantity',
                'Unit',
                'Vendor',
                'Vehicle Number',
                'Driver Name',
                'Driver Phone',
                'Status',
                'Dispatched At',
                'Delivered At',
                'Notes',
                'Delivery Notes',
                'Created At',
            ]);

            // CSV Data
            foreach ($transportations as $t) {
                fputcsv($file, [
                    $t->transportation_number,
                    $t->transportation_date->format('Y-m-d'),
                    $t->waste_record->record_number ?? 'N/A',
                    $t->waste_record->waste_type->name ?? 'N/A',
                    $t->waste_record->waste_type->category->name ?? 'N/A',
                    $t->quantity,
                    $t->unit,
                    $t->vendor->name ?? 'N/A',
                    $t->vehicle_number ?? 'N/A',
                    $t->driver_name ?? 'N/A',
                    $t->driver_phone ?? 'N/A',
                    $t->getStatusLabel(),
                    $t->dispatched_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $t->delivered_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $t->notes ?? 'N/A',
                    $t->delivery_notes ?? 'N/A',
                    $t->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
