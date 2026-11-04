<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileReferenceController extends ApiController
{
    public function users(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.view'), 403);

        return $this->success(User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with('role:id,name,slug')
            ->orderBy('name')->get(['id', 'name', 'email', 'role_id']));
    }

    public function user(Request $request, string $id): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.view'), 403);

        return $this->success(User::query()->where('organization_id', $request->user()->organization_id)
            ->with('role:id,name,slug')->select(['id', 'name', 'email', 'role_id'])->findOrFail($id));
    }

    public function roles(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('roles.view'), 403);

        return $this->success(Role::query()->orderBy('name')->get(['id', 'name', 'slug']));
    }

    public function role(Request $request, string $id): JsonResponse
    {
        abort_unless($request->user()->hasPermission('roles.view'), 403);

        return $this->success(Role::query()->with('permissions:id,name,slug,module')->findOrFail($id));
    }
}
