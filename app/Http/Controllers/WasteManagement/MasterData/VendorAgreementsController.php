<?php

namespace App\Http\Controllers\WasteManagement\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\MasterData\VendorAgreementRequest;
use App\Models\VendorAgreement;
use Illuminate\Http\RedirectResponse;

class VendorAgreementsController extends Controller
{
    public function store(VendorAgreementRequest $request): RedirectResponse
    {
        VendorAgreement::create([...$request->validated(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'MoU tersimpan. Status tercatat bukan pengesahan legal.');
    }

    public function update(VendorAgreementRequest $request, VendorAgreement $agreement): RedirectResponse
    {
        $agreement->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        return back()->with('success', 'MoU diperbarui.');
    }
}
