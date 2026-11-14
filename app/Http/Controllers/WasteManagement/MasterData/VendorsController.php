<?php

namespace App\Http\Controllers\WasteManagement\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\MasterData\VendorRequest;
use App\Models\Vendor;
use App\Models\VendorAgreement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
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
            'agreementsReady' => Schema::hasTable('vendor_agreements'),
            'agreements' => Schema::hasTable('vendor_agreements') ? VendorAgreement::with(['transporter:id,name', 'receiver:id,name'])->latest()->get()->map(fn (VendorAgreement $agreement) => [...$agreement->toArray(), 'valid_from' => $agreement->valid_from?->format('Y-m-d'), 'valid_until' => $agreement->valid_until?->format('Y-m-d'), 'period_status' => $agreement->periodStatus()]) : [],
        ]);
    }

    /**
     * Store a newly created vendor.
     */
    public function store(VendorRequest $request): RedirectResponse
    {
        $vendor = Vendor::create([
            ...$request->safe()->only(['is_transporter', 'is_processor', 'is_utilizer']),
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
            ...$request->safe()->only(['is_transporter', 'is_processor', 'is_utilizer']),
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
        if ((Schema::hasTable('vendor_agreements') && VendorAgreement::where('transporter_vendor_id', $vendor->id)->orWhere('receiver_vendor_id', $vendor->id)->exists()) || $vendor->fabaMovements()->exists() || (\Illuminate\Support\Facades\Schema::hasColumn('waste_haulings', 'vendor_id') && \App\Models\WasteHauling::withTrashed()->where('vendor_id', $vendor->id)->exists())) {
            return Redirect::back()
                ->with('error', 'Vendor memiliki MoU atau riwayat transaksi FABA/pengangkutan dan tidak dapat dihapus. Nonaktifkan vendor bila tidak lagi digunakan.');
        }

        $vendor->delete();

        return Redirect::route('waste-management.master-data.vendors.index')
            ->with('success', 'Vendor deleted successfully.');
    }
}
