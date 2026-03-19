<?php

namespace App\Http\Controllers\WasteManagement\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\MasterData\VendorRequest;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class VendorsController extends Controller
{
    /**
     * Display a listing of vendors.
     */
    public function index(): Response
    {
        $vendors = Vendor::withCount('transportations')
            ->orderBy('name')
            ->get();

        return Inertia::render('waste-management/master-data/Vendors', [
            'vendors' => $vendors,
        ]);
    }

    /**
     * Store a newly created vendor.
     */
    public function store(VendorRequest $request): RedirectResponse
    {
        $vendor = Vendor::create([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'description' => $request->validated('description'),
            'contact_person' => $request->validated('contact_person'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'address' => $request->validated('address'),
            'license_number' => $request->validated('license_number'),
            'license_expiry_date' => $request->validated('license_expiry_date'),
            'is_active' => $request->validated('is_active') ?? true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('waste-management.master-data.vendors.index')
            ->with('success', 'Vendor created successfully.');
    }

    /**
     * Update the specified vendor.
     */
    public function update(VendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'description' => $request->validated('description'),
            'contact_person' => $request->validated('contact_person'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'address' => $request->validated('address'),
            'license_number' => $request->validated('license_number'),
            'license_expiry_date' => $request->validated('license_expiry_date'),
            'is_active' => $request->validated('is_active') ?? true,
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('waste-management.master-data.vendors.index')
            ->with('success', 'Vendor updated successfully.');
    }

    /**
     * Remove the specified vendor.
     */
    public function destroy(Vendor $vendor): RedirectResponse
    {
        // Check if vendor has transportations
        if ($vendor->transportations()->count() > 0) {
            return Redirect::back()
                ->with('error', 'Cannot delete vendor with associated transportations. Please delete the transportations first.');
        }

        $vendor->delete();

        return Redirect::route('waste-management.master-data.vendors.index')
            ->with('success', 'Vendor deleted successfully.');
    }
}
