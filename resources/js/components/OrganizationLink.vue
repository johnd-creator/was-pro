<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';
import { recordNavigationHeaders } from '@/lib/record-list';
import { index as recordsIndex } from '@/routes/waste-management/records';

defineOptions({ inheritAttrs: false });
const props = defineProps<{
    href: InertiaLinkProps['href'];
    method?: InertiaLinkProps['method'];
    headers?: InertiaLinkProps['headers'];
}>();
const { scopedUrl } = useOrganizationContext();
const href = computed(() => {
    if (typeof props.href === 'string') return scopedUrl(props.href);
    if (props.href) return { ...props.href, url: scopedUrl(props.href.url) };
    return props.href;
});
const headers = computed(() =>
    recordNavigationHeaders(
        href.value,
        recordsIndex.url(),
        typeof window === 'undefined'
            ? 'http://localhost'
            : window.location.origin,
        props.method,
        props.headers,
    ),
);
</script>

<template>
    <Link v-bind="$attrs" :href="href" :method="props.method" :headers="headers"
        ><slot
    /></Link>
</template>
