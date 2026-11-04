<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\WasteManagement\MasterData\CategoryRequest;
use App\Models\WasteCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileWasteCategoryController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_categories.view'), 403);
        $page = WasteCategory::query()->orderBy('name')->orderBy('id')->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return $this->success(collect($page->items())->map(fn ($item) => $this->serialize($item, $request))->all(), meta: [
            'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ]);
    }

    public function show(Request $request, WasteCategory $category): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_categories.view'), 403);

        return $this->success($this->serialize($category, $request));
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_categories.create'), 403);
        $model = WasteCategory::create([...$request->validated(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return $this->success($this->serialize($model, $request), 'Data berhasil disimpan.', status: 201);
    }

    public function update(CategoryRequest $request, WasteCategory $category): JsonResponse
    {
        abort_unless($request->user()->hasPermission('waste_categories.edit'), 403);
        $category->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        return $this->success($this->serialize($category, $request), 'Data berhasil diperbarui.');
    }

    private function serialize(WasteCategory $model, Request $request): array
    {
        $model->refresh();
        $data = $model->toArray();
        $data['resource_version'] = \App\Services\ResourceVersion::of($model);
        $data['allowed_actions'] = ['view'];
        if ($request->user()->hasPermission('waste_categories.edit')) {
            $data['allowed_actions'][] = 'update';
        }

        return $data;
    }
}
