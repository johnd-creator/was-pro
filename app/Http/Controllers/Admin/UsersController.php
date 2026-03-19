<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $organizationId = auth()->user()->organization_id;

        $users = User::when($organizationId, function ($query) use ($organizationId) {
            return $query->where('organization_id', $organizationId);
        })
            ->with(['organization', 'role'])
            ->orderBy('name')
            ->get();

        $organizations = Organization::active()->get();
        $roles = Role::active()->get();

        return Inertia::render('admin/Users', [
            'users' => $users,
            'organizations' => $organizations,
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(): RedirectResponse
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'organization_id' => 'required|uuid|exists:organizations,id',
            'role_id' => 'required|uuid|exists:roles,id',
            'is_super_admin' => 'boolean',
        ]);

        // Check authorization
        if (! auth()->user()->canManageUsers($validated['organization_id'])) {
            abort(403, 'You do not have permission to create users in this organization.');
        }

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'organization_id' => $validated['organization_id'],
            'role_id' => $validated['role_id'],
            'is_super_admin' => $validated['is_super_admin'] ?? false,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Update the specified user.
     */
    public function update(User $user): RedirectResponse
    {
        // Check authorization
        if (! auth()->user()->canManageUsers($user->organization_id)) {
            abort(403, 'You do not have permission to edit users in this organization.');
        }

        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'organization_id' => 'required|uuid|exists:organizations,id',
            'role_id' => 'required|uuid|exists:roles,id',
            'is_super_admin' => 'boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'organization_id' => $validated['organization_id'],
            'role_id' => $validated['role_id'],
            'is_super_admin' => $validated['is_super_admin'] ?? false,
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
        if (! auth()->user()->canManageUsers($user->organization_id)) {
            abort(403, 'You do not have permission to edit users in this organization.');
        }

        $validated = request()->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('admin.users.index')
            ->with('success', 'Password updated successfully.');
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): RedirectResponse
    {
        // Check authorization
        if (! auth()->user()->canManageUsers($user->organization_id)) {
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
}
