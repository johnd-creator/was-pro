<?php

namespace App\Http\Controllers\WasteManagement\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\MasterData\WasteTypeRequest;
use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class TypesController extends Controller
{
    /**
     * Display a listing of waste types.
     */
    public function index(): Response
    {
        $wasteTypes = WasteType::with(['category', 'characteristic'])
            ->orderBy('name')
            ->get();

        $categories = WasteCategory::active()->orderBy('name')->get();
        $characteristics = WasteCharacteristic::active()->orderBy('name')->get();

        return Inertia::render('waste-management/master-data/Types', [
            'wasteTypes' => $wasteTypes,
            'categories' => $categories,
            'characteristics' => $characteristics,
        ]);
    }

    /**
     * Store a newly created waste type.
     */
    public function store(WasteTypeRequest $request): RedirectResponse
    {
        $wasteType = WasteType::create([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'category_id' => $request->validated('category_id'),
            'characteristic_id' => $request->validated('characteristic_id'),
            'description' => $request->validated('description'),
            'storage_period_days' => $request->validated('storage_period_days'),
            'transport_cost' => $request->validated('transport_cost'),
            'is_active' => $request->validated('is_active') ?? true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('waste-management.master-data.types.index')
            ->with('success', 'Waste type created successfully.');
    }

    /**
     * Update the specified waste type.
     */
    public function update(WasteTypeRequest $request, WasteType $wasteType): RedirectResponse
    {
        $wasteType->update([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'category_id' => $request->validated('category_id'),
            'characteristic_id' => $request->validated('characteristic_id'),
            'description' => $request->validated('description'),
            'storage_period_days' => $request->validated('storage_period_days'),
            'transport_cost' => $request->validated('transport_cost'),
            'is_active' => $request->validated('is_active') ?? true,
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('waste-management.master-data.types.index')
            ->with('success', 'Waste type updated successfully.');
    }

    /**
     * Remove the specified waste type.
     */
    public function destroy(WasteType $wasteType): RedirectResponse
    {
        // Check if waste type has waste records
        if ($wasteType->wasteRecords()->count() > 0) {
            return Redirect::back()
                ->with('error', 'Cannot delete waste type with associated waste records. Please delete the waste records first.');
        }

        $wasteType->delete();

        return Redirect::route('waste-management.master-data.types.index')
            ->with('success', 'Waste type deleted successfully.');
    }
}
