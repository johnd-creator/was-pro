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
        $vendors = Vendor::withCount(['fabaMovements', 'fabaMovements as transportations_count'])
            ->orderBy('name')
            ->get();

        return Inertia::render('waste-management/master-data/Vendors', [
            'vendors' => $vendors->map(fn (Vendor $vendor) => [...$vendor->toArray(), 'license_expiry_day' => $vendor->license_expiry_date?->format('Y-m-d')]),
            'licenseReferenceDate' => now()->toDateString(),
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
        // Preserve vendor references in operational history.
        if ($vendor->fabaMovements()->exists() || (\Illuminate\Support\Facades\Schema::hasColumn('waste_haulings', 'vendor_id') && \App\Models\WasteHauling::withTrashed()->where('vendor_id', $vendor->id)->exists())) {
            return Redirect::back()
                ->with('error', 'Vendor memiliki riwayat transaksi FABA atau pengangkutan dan tidak dapat dihapus. Nonaktifkan vendor bila tidak lagi digunakan.');
        }

        $vendor->delete();

        return Redirect::route('waste-management.master-data.vendors.index')
            ->with('success', 'Vendor deleted successfully.');
    }
}
