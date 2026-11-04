<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\MasterData\CharacteristicRequest;
use App\Models\WasteCharacteristic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileWasteCharacteristicController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_characteristics.view'), 403);
        $page = WasteCharacteristic::query()->orderBy('name')->orderBy('id')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return $this->success(collect($page->items())->map(fn ($item) => $this->serialize($item, $request))->all(), meta: [
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ]);
    }

    public function show(Request $request, WasteCharacteristic $characteristic): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_characteristics.view'), 403);

        return $this->success($this->serialize($characteristic, $request));
    }

    public function store(CharacteristicRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_characteristics.create'), 403);
        $model = WasteCharacteristic::create([...$request->validated(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return $this->success($this->serialize($model, $request), 'Data berhasil disimpan.', status: 201);
    }

    public function update(CharacteristicRequest $request, WasteCharacteristic $characteristic): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_characteristics.edit'), 403);
        $characteristic->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        return $this->success($this->serialize($characteristic, $request), 'Data berhasil diperbarui.');
    }

    private function serialize(WasteCharacteristic $model, Request $request): array
    {
        $model->refresh();
        $data = $model->toArray();
        $data['resource_version'] = \App\Services\ResourceVersion::of($model);
        $data['allowed_actions'] = ['view'];
        if ($request->user()->hasPermission('waste_characteristics.edit')) {
            $data['allowed_actions'][] = 'update';
        }

        return $data;
    }
}
