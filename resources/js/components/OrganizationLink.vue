<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useOrganizationContext } from '@/composables/useOrganizationContext';

defineOptions({ inheritAttrs: false });
const props = defineProps<{ href: InertiaLinkProps['href'] }>();
const { scopedUrl } = useOrganizationContext();
const href = computed(() => {
    if (typeof props.href === 'string') return scopedUrl(props.href);
    if (props.href) return { ...props.href, url: scopedUrl(props.href.url) };
    return props.href;
});
</script>

<template>
    <Link v-bind="$attrs" :href="href"><slot /></Link>
</template>
