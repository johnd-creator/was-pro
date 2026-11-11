export function scopeOrganizationUrl(
    href: string,
    organizationId: string,
    origin: string,
): string {
    const url = new URL(href, origin);
    if (
        url.origin !== origin ||
        !(
            url.pathname === '/dashboard' ||
            url.pathname.startsWith('/waste-management/')
        )
    ) {
        return href;
    }

    if (!url.searchParams.has('organization_id')) {
        url.searchParams.set('organization_id', organizationId);
    }

    return `${url.pathname}${url.search}${url.hash}`;
}
