import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface PermissionChecks {
    hasPermission: (permission: string) => boolean;
    hasRole: (role: string) => boolean;
    isSuperAdmin: () => boolean;
    canAccessOrganization: (organizationId: string) => boolean;
    canManageUsers: (organizationId?: string) => boolean;
}

export function usePermissions(): PermissionChecks {
    const page = usePage();
    const user = computed(() => {
        return page.props.auth?.user as
            | {
                  is_super_admin?: boolean;
                  permissions?: string[];
                  role?: {
                      slug?: string;
                  } | null;
                  organization_id?: string | number | null;
              }
            | undefined;
    });

    const hasPermission = (permission: string): boolean => {
        if (!user.value) return false;
        if (user.value.is_super_admin) return true;

        // Check if user has role with permission
        // This would need to be populated from backend
        return user.value.permissions?.includes(permission) || false;
    };

    const hasRole = (role: string): boolean => {
        if (!user.value) return false;
        if (user.value.is_super_admin) return true;

        return user.value.role?.slug === role;
    };

    const isSuperAdmin = (): boolean => {
        return user.value?.is_super_admin || false;
    };

    const canAccessOrganization = (organizationId: string): boolean => {
        if (!user.value) return false;
        if (user.value.is_super_admin) return true;

        return String(user.value.organization_id) === organizationId;
    };

    const canManageUsers = (organizationId?: string): boolean => {
        if (!user.value) return false;
        if (user.value.is_super_admin) return true;

        // Admins can manage users in their own organization
        if (user.value.role?.slug === 'admin') {
            if (organizationId) {
                return String(user.value.organization_id) === organizationId;
            }
            return true;
        }

        return hasPermission('users.manage');
    };

    return {
        hasPermission,
        hasRole,
        isSuperAdmin,
        canAccessOrganization,
        canManageUsers,
    };
}

export default usePermissions;
