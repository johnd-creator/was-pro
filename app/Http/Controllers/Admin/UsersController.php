<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(): Response
    {
        $viewer = auth()->user();

        $users = User::when(! $viewer->isSuperAdmin(), function ($query) use ($viewer) {
            return $query->where('organization_id', $viewer->organization_id);
        })
            ->with(['organization', 'role'])
            ->orderBy('name')
            ->get();

        $organizations = Organization::active()->when(! $viewer->isSuperAdmin(), fn ($query) => $query->where('id', $viewer->organization_id))->get();
        $roles = Role::active()->orderByDesc('level')->orderBy('name')->get();

        return Inertia::render('admin/Users', [
            'users' => $users,
            'organizations' => $organizations,
            'roles' => $roles,
            'canManageSuperAdmins' => $viewer->isSuperAdmin(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(SaveUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'organization_id' => $request->superAdminStatus() ? null : $validated['organization_id'],
            'role_id' => $validated['role_id'],
            'is_super_admin' => $request->superAdminStatus(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Update the specified user.
     */
    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'organization_id' => $request->superAdminStatus() ? null : $validated['organization_id'],
            'role_id' => $validated['role_id'],
            'is_super_admin' => $request->superAdminStatus(),
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Update the specified user's password.
     */
    public function updatePassword(User $user): RedirectResponse
    {
        // Check authorization
        if (! $this->canManageTarget($user)) {
            abort(403, 'You do not have permission to edit users in this organization.');
        }

        $validated = request()->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->getConnection()->transaction(function () use ($user, $validated): void {
            $user->update([
                'password' => Hash::make($validated['password']),
                'updated_by' => auth()->id(),
            ]);
            $user->apiTokens()->delete();
        });

        return Redirect::route('admin.users.index')
            ->with('success', 'Password updated successfully.');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        // Check authorization
        if (! $this->canManageTarget($user)) {
            abort(403, 'You do not have permission to delete users in this organization.');
        }

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return Redirect::back()
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return Redirect::route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    private function canManageTarget(User $target): bool
    {
        $actor = auth()->user();

        return $actor->isSuperAdmin() || (! $target->isSuperAdmin()
            && $actor->organization_id !== null
            && $actor->organization_id === $target->organization_id
            && $actor->canManageUsers($target->organization_id));
    }
}
