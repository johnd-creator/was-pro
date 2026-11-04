<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\MasterData\WasteTypeRequest;
use App\Models\WasteType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileWasteTypeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_types.view'), 403);
        $page = WasteType::query()->orderBy('name')->orderBy('id')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return $this->success(collect($page->items())->map(fn ($item) => $this->serialize($item, $request))->all(), meta: [
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ]);
    }

    public function show(Request $request, WasteType $wasteType): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_types.view'), 403);

        return $this->success($this->serialize($wasteType, $request));
    }

    public function store(WasteTypeRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_types.create'), 403);
        $model = WasteType::create([...$request->validated(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return $this->success($this->serialize($model, $request), 'Data berhasil disimpan.', status: 201);
    }

    public function update(WasteTypeRequest $request, WasteType $wasteType): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_types.edit'), 403);
        $wasteType->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        return $this->success($this->serialize($wasteType, $request), 'Data berhasil diperbarui.');
    }

    private function serialize(WasteType $model, Request $request): array
    {
        $model->refresh();
        $data = $model->toArray();
        $data['resource_version'] = \App\Services\ResourceVersion::of($model);
        $data['allowed_actions'] = ['view'];
        if ($request->user()->hasPermission('waste_types.edit')) {
            $data['allowed_actions'][] = 'update';
        }

        return $data;
    }
}
