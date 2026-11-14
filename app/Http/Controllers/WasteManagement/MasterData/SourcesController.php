<?php

namespace App\Http\Controllers\WasteManagement\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\MasterData\SourceRequest;
use App\Models\WasteSource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SourcesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('waste-management/master-data/Sources', ['sources' => WasteSource::available() ? WasteSource::orderBy('name')->get() : [], 'available' => WasteSource::available(), 'abilities' => ['create' => auth()->user()->hasPermission('waste_categories.create'), 'edit' => auth()->user()->hasPermission('waste_categories.edit')]]);
    }

    public function store(SourceRequest $request): RedirectResponse
    {
        abort_unless(WasteSource::available(), 409, 'Master sumber belum diaktifkan.');
        WasteSource::create($request->validated());

        return back()->with('success', 'Sumber limbah ditambahkan.');
    }

    public function update(SourceRequest $request, string $source): RedirectResponse
    {
        abort_unless(WasteSource::available(), 409, 'Master sumber belum diaktifkan.');
        WasteSource::findOrFail($source)->update($request->validated());

        return back()->with('success', 'Sumber limbah diperbarui.');
    }
}
