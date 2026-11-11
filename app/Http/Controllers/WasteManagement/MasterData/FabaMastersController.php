<?php

namespace App\Http\Controllers\WasteManagement\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\MasterData\FabaMasterRequest;
use App\Models\FabaInternalDestination;
use App\Models\FabaPurpose;
use App\Services\OperationalInbox;
use App\Services\OperationalTransactionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FabaMastersController extends Controller
{
    private function model(string $kind): string
    {
        abort_unless(in_array($kind, ['destinations', 'purposes'], true), 404);
        $organization = app(OperationalInbox::class)->organization();
        abort_unless($organization && auth()->user()->canAccessOrganization((string) $organization->id), 403);

        return $kind === 'destinations' ? FabaInternalDestination::class : FabaPurpose::class;
    }

    public function index(string $kind): Response
    {
        $model = $this->model($kind);

        return Inertia::render('waste-management/master-data/FabaMasters', ['kind' => $kind, 'items' => $model::query()->withCount('movements')->orderBy('name')->get(), 'canCreate' => auth()->user()->hasPermission('faba_master.create'), 'canEdit' => auth()->user()->hasPermission('faba_master.edit')]);
    }

    public function store(FabaMasterRequest $request, string $kind): RedirectResponse
    {
        $model = $this->model($kind);
        app(OperationalTransactionService::class)->run(fn () => $model::query()->create($request->validated()));

        return back()->with('success', 'Data master ditambahkan.');
    }

    public function update(FabaMasterRequest $request, string $kind, string $record): RedirectResponse
    {
        $model = $this->model($kind);
        app(OperationalTransactionService::class)->run(fn () => $model::query()->findOrFail($record)->update($request->safe()->except('slug')));

        return back()->with('success', 'Data master diperbarui. Riwayat transaksi tetap tersimpan.');
    }
}
