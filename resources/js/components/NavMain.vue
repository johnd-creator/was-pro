<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem, NavItemWithRequiredHref } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const page = usePage();
const user = computed(() => page.props.auth?.user);

// Collapsible sections state with localStorage persistence
const STORAGE_KEY = 'sidebar-sections-state';

const getStoredState = (): Record<string, boolean> => {
    if (typeof window === 'undefined') return {};
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return stored ? JSON.parse(stored) : {};
    } catch {
        return {};
    }
};

const storeState = (state: Record<string, boolean>) => {
    if (typeof window === 'undefined') return;
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        // Ignore storage errors
    }
};

const collapsedSections = ref<Record<string, boolean>>(getStoredState());

// Smart default collapse states based on section importance
const getDefaultCollapsed = (title: string): boolean => {
    const stored = getStoredState();
    if (stored[title] !== undefined) {
        return stored[title];
    }

    // Smart defaults
    const alwaysExpanded = ['Dashboard', 'Operasional Limbah'];
    const alwaysCollapsed = ['Data Master', 'Administration'];

    if (alwaysExpanded.includes(title)) return false;
    if (alwaysCollapsed.includes(title)) return true;

    return false; // Default to expanded
};

const isSectionCollapsed = (title: string): boolean => {
    return collapsedSections.value[title] ?? getDefaultCollapsed(title);
};

const toggleSection = (title: string) => {
    collapsedSections.value = {
        ...collapsedSections.value,
        [title]: !isSectionCollapsed(title),
    };
    storeState(collapsedSections.value);
};

// Watch for changes and persist
watch(
    collapsedSections,
    (newState) => {
        storeState(newState);
    },
    { deep: true },
);

function canView(item: NavItem): boolean {
    if (user.value?.is_super_admin === true) {
        return true;
    }

    if (item.permission && user.value?.permissions?.includes(item.permission)) {
        return true;
    }

    if (
        item.permissions &&
        item.permissions.some((permission) =>
            user.value?.permissions?.includes(permission),
        )
    ) {
        return true;
    }

    if (!item.permission && !item.permissions) {
        return true;
    }

    return false;
}

function filterVisibleItems(items: NavItem[]): NavItem[] {
    return items
        .map((item) => ({
            ...item,
            items: item.items ? filterVisibleItems(item.items) : undefined,
        }))
        .filter((item) => {
            if (item.items && item.items.length > 0) {
                return true;
            }

            return canView(item);
        });
}

function hasHref(item: NavItem): item is NavItemWithRequiredHref {
    return !!item.href;
}

function isDirectItem(item: NavItem): item is NavItemWithRequiredHref {
    return hasHref(item) && !item.items;
}

const visibleItems = computed(() => filterVisibleItems(props.items));

const directItems = computed(() => visibleItems.value.filter(isDirectItem));

const sectionItems = computed<NavItem[]>(() =>
    visibleItems.value.filter((item) => item.items && item.items.length > 0),
);
</script>

<template>
    <div class="space-y-3 px-2 py-2">
        <SidebarMenu v-if="directItems.length > 0" class="gap-1">
            <SidebarMenuItem v-for="item in directItems" :key="item.title">
                <SidebarMenuButton
                    as-child
                    size="lg"
                    :is-active="isCurrentUrl(item.href)"
                    class="group/direct h-auto rounded-xl border border-sidebar-border bg-sidebar-accent/30 px-3 py-2.5 transition-colors hover:bg-sidebar-accent/50 data-[active=true]:bg-sidebar-primary/90 data-[active=true]:text-sidebar-primary-foreground data-[active=true]:shadow-sm"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" class="size-4" />
                        <div
                            class="grid flex-1 text-left group-data-[collapsible=icon]:hidden"
                        >
                            <span
                                class="text-sm font-semibold text-sidebar-foreground"
                                >{{ item.title }}</span
                            >
                            <span
                                v-if="item.description"
                                class="text-xs text-sidebar-foreground/80 opacity-0 transition-opacity duration-200 group-hover/direct:opacity-100"
                            >
                                {{ item.description }}
                            </span>
                        </div>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>

        <SidebarGroup
            v-for="section in sectionItems"
            :key="section.title"
            class="px-0 py-0"
        >
            <SidebarGroupLabel
                class="flex cursor-pointer items-center justify-between px-2 text-[11px] font-semibold tracking-[0.18em] text-sidebar-foreground/75 uppercase transition-colors select-none hover:text-sidebar-foreground"
                @click="toggleSection(section.title)"
            >
                <span>{{ section.title }}</span>
                <component
                    :is="
                        isSectionCollapsed(section.title)
                            ? ChevronRight
                            : ChevronDown
                    "
                    class="size-3 transition-transform duration-200"
                />
            </SidebarGroupLabel>
            <p
                v-if="section.description && !isSectionCollapsed(section.title)"
                class="px-2 pb-2 text-xs leading-5 text-sidebar-foreground/70 group-data-[collapsible=icon]:hidden"
            >
                {{ section.description }}
            </p>
            <SidebarMenu
                v-show="!isSectionCollapsed(section.title)"
                class="gap-1"
            >
                <SidebarMenuItem
                    v-for="item in section.items"
                    :key="`${section.title}-${item.title}`"
                >
                    <template v-if="hasHref(item)">
                        <SidebarMenuButton
                            as-child
                            :is-active="isCurrentUrl(item.href)"
                            class="group/item h-auto rounded-xl px-3 py-2 transition-colors hover:bg-sidebar-accent data-[active=true]:bg-sidebar-accent/80 data-[active=true]:font-semibold data-[active=true]:text-sidebar-foreground"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" class="size-4" />
                                <div
                                    class="grid flex-1 text-left group-data-[collapsible=icon]:hidden"
                                >
                                    <span
                                        class="text-sm font-medium text-sidebar-foreground"
                                        >{{ item.title }}</span
                                    >
                                    <span
                                        v-if="item.description"
                                        class="text-xs leading-5 text-sidebar-foreground/75 opacity-0 transition-opacity duration-200 group-hover/item:opacity-100"
                                    >
                                        {{ item.description }}
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </template>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </div>
</template>
