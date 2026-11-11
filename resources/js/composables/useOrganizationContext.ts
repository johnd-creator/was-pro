import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { scopeOrganizationUrl } from '@/lib/organization-context';

type Organization = { id: string; name: string; code: string };
type OrganizationContext = {
    organization: Organization | null;
    can_select: boolean;
    is_global: boolean;
    choices: Organization[];
};

export function useOrganizationContext() {
    const page = usePage();
    const context = computed(
        () => page.props.organizationContext as OrganizationContext | undefined,
    );

    function scopedUrl(href: string | { url: string }): string {
        const url = typeof href === 'string' ? href : href.url;
        const organization = context.value?.organization;
        if (!context.value?.can_select || !organization) return url;
        const base =
            typeof window === 'undefined'
                ? 'http://localhost'
                : window.location.origin;

        return scopeOrganizationUrl(url, organization.id, base);
    }

    function download(href: string): void {
        window.location.assign(scopedUrl(href));
    }

    return { context, scopedUrl, download };
}
