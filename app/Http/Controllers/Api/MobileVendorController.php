<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\MasterData\VendorRequest;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileVendorController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('vendors.view'), 403);
        $page = Vendor::query()->orderBy('name')->orderBy('id')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return $this->success(collect($page->items())->map(fn ($item) => $this->serialize($item, $request))->all(), meta: [
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ]);
    }

    public function show(Request $request, Vendor $vendor): JsonResponse
    {
        abort_unless($request->user()->hasPermission('vendors.view'), 403);

        return $this->success($this->serialize($vendor, $request));
    }

    public function store(VendorRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('vendors.create'), 403);
        $model = Vendor::create([...$request->validated(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return $this->success($this->serialize($model, $request), 'Data berhasil disimpan.', status: 201);
    }

    public function update(VendorRequest $request, Vendor $vendor): JsonResponse
    {
        abort_unless($request->user()->hasPermission('vendors.edit'), 403);
        $vendor->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        return $this->success($this->serialize($vendor, $request), 'Data berhasil diperbarui.');
    }

    private function serialize(Vendor $model, Request $request): array
    {
        $model->refresh();
        $data = $model->toArray();
        $data['license_expiry_date'] = $model->license_expiry_date?->format('Y-m-d');
        $data['resource_version'] = \App\Services\ResourceVersion::of($model);
        $data['allowed_actions'] = ['view'];
        if ($request->user()->hasPermission('vendors.edit')) {
            $data['allowed_actions'][] = 'update';
        }

        return $data;
    }
}
