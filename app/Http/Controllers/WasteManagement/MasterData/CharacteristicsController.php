<?php

namespace App\Http\Controllers\WasteManagement\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\MasterData\CharacteristicRequest;
use App\Models\WasteCharacteristic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class CharacteristicsController extends Controller
{
    /**
     * Display a listing of waste characteristics.
     */
    public function index(): Response
    {
        $characteristics = WasteCharacteristic::withCount('wasteTypes')
            ->orderBy('name')
            ->get();

        return Inertia::render('waste-management/master-data/Characteristics', [
            'characteristics' => $characteristics,
        ]);
    }

    /**
     * Store a newly created characteristic.
     */
    public function store(CharacteristicRequest $request): RedirectResponse
    {
        $characteristic = WasteCharacteristic::create([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'description' => $request->validated('description'),
            'is_hazardous' => $request->validated('is_hazardous') ?? false,
            'is_active' => $request->validated('is_active') ?? true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('waste-management.master-data.characteristics.index')
            ->with('success', 'Characteristic created successfully.');
    }

    /**
     * Update the specified characteristic.
     */
    public function update(CharacteristicRequest $request, WasteCharacteristic $characteristic): RedirectResponse
    {
        $characteristic->update([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'description' => $request->validated('description'),
            'is_hazardous' => $request->validated('is_hazardous') ?? false,
            'is_active' => $request->validated('is_active') ?? true,
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('waste-management.master-data.characteristics.index')
            ->with('success', 'Characteristic updated successfully.');
    }

    /**
     * Remove the specified characteristic.
     */
    public function destroy(WasteCharacteristic $characteristic): RedirectResponse
    {
        // Check if characteristic has waste types
        if ($characteristic->wasteTypes()->count() > 0) {
            return Redirect::back()
                ->with('error', 'Cannot delete characteristic with associated waste types. Please reassign or delete the waste types first.');
        }

        $characteristic->delete();

        return Redirect::route('waste-management.master-data.characteristics.index')
            ->with('success', 'Characteristic deleted successfully.');
    }
}
