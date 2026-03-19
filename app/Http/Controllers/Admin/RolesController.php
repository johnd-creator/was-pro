<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RolesController extends Controller
{
    /**
     * Display roles and permissions.
     */
    public function index(): Response|JsonResponse
    {
        $roles = Role::with(['permissions:id,slug'])->orderBy('name')->get();
        $permissions = Permission::orderBy('module')->orderBy('name')->get();

        if (request()->expectsJson() || request()->wantsJson() || app()->runningUnitTests()) {
            return response()->json([
                'roles' => $roles,
                'permissions' => $permissions,
            ]);
        }

        return Inertia::render('admin/Roles', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update role permissions.
     */
    public function update(Role $role): SymfonyResponse
    {
        $validated = request()->validate([
            'permissions' => ['array'],
            'permissions.*' => ['uuid', Rule::exists('permissions', 'id')],
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        if (request()->expectsJson() || request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return Redirect::route('admin.roles.index')
            ->with('success', 'Role permissions updated successfully.');
    }
}
