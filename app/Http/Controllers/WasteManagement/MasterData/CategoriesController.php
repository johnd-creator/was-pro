<?php

namespace App\Http\Controllers\WasteManagement\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\MasterData\CategoryRequest;
use App\Models\WasteCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class CategoriesController extends Controller
{
    /**
     * Display a listing of waste categories.
     */
    public function index(): Response
    {
        $categories = WasteCategory::withCount('wasteTypes')
            ->orderBy('name')
            ->get();

        return Inertia::render('waste-management/master-data/Categories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = WasteCategory::create([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'description' => $request->validated('description'),
            'is_active' => $request->validated('is_active') ?? true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('waste-management.master-data.categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Update the specified category.
     */
    public function update(CategoryRequest $request, WasteCategory $category): RedirectResponse
    {
        $category->update([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'description' => $request->validated('description'),
            'is_active' => $request->validated('is_active') ?? true,
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('waste-management.master-data.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(WasteCategory $category): RedirectResponse
    {
        // Check if category has waste types
        if ($category->wasteTypes()->count() > 0) {
            return Redirect::back()
                ->with('error', 'Cannot delete category with associated waste types. Please reassign or delete the waste types first.');
        }

        $category->delete();

        return Redirect::route('waste-management.master-data.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
