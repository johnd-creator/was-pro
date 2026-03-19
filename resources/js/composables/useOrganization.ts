import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

interface Organization {
    id: string;
    name: string;
    code: string;
    schema_name: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
}

interface OrganizationChecks {
    currentOrganization: ComputedRef<Organization | null>;
    organizationId: ComputedRef<string | null>;
    hasOrganization: ComputedRef<boolean>;
    organizationName: ComputedRef<string>;
    organizationCode: ComputedRef<string>;
}

export function useOrganization(): OrganizationChecks {
    const page = usePage();
    const user = computed(() => {
        return page.props.auth?.user as
            | {
                  organization?: Organization | null;
                  organization_id?: string | number | null;
              }
            | undefined;
    });

    const currentOrganization = computed<Organization | null>(() => {
        return user.value?.organization || null;
    });

    const organizationId = computed<string | null>(() => {
        if (!user.value?.organization_id) {
            return null;
        }

        return String(user.value.organization_id);
    });

    const hasOrganization = computed<boolean>(() => {
        return !!user.value?.organization_id;
    });

    const organizationName = computed<string>(() => {
        return user.value?.organization?.name || 'No Organization';
    });

    const organizationCode = computed<string>(() => {
        return user.value?.organization?.code || '';
    });

    return {
        currentOrganization,
        organizationId,
        hasOrganization,
        organizationName,
        organizationCode,
    };
}

export default useOrganization;
