<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class AuthorizationService
{
    /**
     * Check if the authenticated user has a specific permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission($permissionSlug);
    }

    /**
     * Check if the authenticated user has a specific role.
     */
    public function hasRole(string $roleSlug): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->hasRole($roleSlug);
    }

    /**
     * Check if the authenticated user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->isSuperAdmin();
    }

    /**
     * Check if the authenticated user can access a specific organization.
     */
    public function canAccessOrganization(string $organizationId): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return $user->canAccessOrganization($organizationId);
    }

    /**
     * Check if the authenticated user can manage users in an organization.
     */
    public function canManageUsers(?string $organizationId = null): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Super admins can manage users in any organization
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admins can manage users in their own organization
        if ($user->hasRole('admin') && $organizationId) {
            return (string) $user->organization_id === $organizationId;
        }

        return $user->hasPermission('users.manage');
    }

    /**
     * Get the user's organization ID.
     */
    public function getUserOrganizationId(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        return $user->organization_id;
    }

    /**
     * Check if the user can access the tenant schema.
     */
    public function canAccessTenantSchema(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Super admins without organization can't access tenant schemas directly
        if ($user->isSuperAdmin() && ! $user->organization_id) {
            return false;
        }

        return $user->organization_id !== null;
    }
}
