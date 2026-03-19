<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Menu } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    NavigationMenuTrigger,
    NavigationMenuContent,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { appNavigationItems } from '@/lib/app-navigation';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const { isCurrentUrl, isCurrentOrParentUrl, whenCurrentUrl } = useCurrentUrl();
const user = computed(() => page.props.auth?.user);

const activeItemStyles =
    'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100';

const mobileExpandedSections = ref<Record<string, boolean>>({});

const availableNavItems = computed(() =>
    appNavigationItems
        .map((item) => filterNavItem(item))
        .filter((item): item is NavItem => item !== null),
);

initializeMobileExpandedSections();

function initializeMobileExpandedSections(): void {
    appNavigationItems.forEach((item) => {
        if (item.items) {
            mobileExpandedSections.value[item.title] = isItemActive(item);
        }
    });
}

function filterNavItem(item: NavItem): NavItem | null {
    if (!canView(item)) {
        return null;
    }

    if (!item.items) {
        return item;
    }

    const filteredChildren = item.items
        .map((child) => filterNavItem(child))
        .filter((child): child is NavItem => child !== null);

    if (filteredChildren.length === 0) {
        return null;
    }

    return {
        ...item,
        items: filteredChildren,
    };
}

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

function hasHref(item: NavItem): item is NavItem & { href: string } {
    return !!item.href;
}

function isItemActive(item: NavItem): boolean {
    if (item.href && isCurrentOrParentUrl(item.href)) {
        return true;
    }

    if (item.items) {
        return item.items.some((child) => isItemActive(child));
    }

    return false;
}

function toggleMobileSection(title: string): void {
    mobileExpandedSections.value[title] = !mobileExpandedSections.value[title];
}

function isMobileSectionExpanded(title: string): boolean {
    return mobileExpandedSections.value[title] ?? false;
}
</script>

<template>
    <div>
        <div class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="mr-2 h-9 w-9"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6">
                            <SheetTitle class="sr-only"
                                >Navigation menu</SheetTitle
                            >
                            <SheetHeader class="flex justify-start text-left">
                                <AppLogoIcon
                                    class="size-6 fill-current text-black dark:text-white"
                                />
                            </SheetHeader>
                            <div
                                class="flex h-full flex-1 flex-col justify-between space-y-4 py-6"
                            >
                                <nav class="-mx-3 space-y-1">
                                    <template
                                        v-for="item in availableNavItems"
                                        :key="item.title"
                                    >
                                        <Link
                                            v-if="hasHref(item)"
                                            :href="item.href"
                                            class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                            :class="
                                                whenCurrentUrl(
                                                    item.href,
                                                    activeItemStyles,
                                                )
                                            "
                                        >
                                            <component
                                                v-if="item.icon"
                                                :is="item.icon"
                                                class="h-5 w-5"
                                            />
                                            {{ item.title }}
                                        </Link>

                                        <div
                                            v-else-if="item.items"
                                            class="rounded-lg border border-border/70 p-2"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-x-3 rounded-md px-2 py-2 text-left text-sm font-medium"
                                                @click="
                                                    toggleMobileSection(
                                                        item.title,
                                                    )
                                                "
                                            >
                                                <component
                                                    v-if="item.icon"
                                                    :is="item.icon"
                                                    class="h-5 w-5"
                                                />
                                                <span>{{ item.title }}</span>
                                                <ChevronDown
                                                    v-if="
                                                        isMobileSectionExpanded(
                                                            item.title,
                                                        )
                                                    "
                                                    class="ml-auto h-4 w-4"
                                                />
                                                <ChevronRight
                                                    v-else
                                                    class="ml-auto h-4 w-4"
                                                />
                                            </button>

                                            <div
                                                v-if="
                                                    isMobileSectionExpanded(
                                                        item.title,
                                                    )
                                                "
                                                class="mt-2 space-y-1"
                                            >
                                                <Link
                                                    v-for="subItem in item.items"
                                                    :key="subItem.title"
                                                    :href="
                                                        hasHref(subItem)
                                                            ? subItem.href
                                                            : '#'
                                                    "
                                                    class="flex items-center gap-x-3 rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-accent hover:text-foreground"
                                                    :class="
                                                        hasHref(subItem) &&
                                                        whenCurrentUrl(
                                                            subItem.href,
                                                            activeItemStyles,
                                                        )
                                                    "
                                                >
                                                    <component
                                                        v-if="subItem.icon"
                                                        :is="subItem.icon"
                                                        class="h-4 w-4"
                                                    />
                                                    {{ subItem.title }}
                                                </Link>
                                            </div>
                                        </div>
                                    </template>
                                </nav>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="dashboard()" class="flex items-center gap-x-2">
                    <AppLogo />
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1">
                    <NavigationMenu class="ml-10 flex h-full items-stretch">
                        <NavigationMenuList
                            class="flex h-full items-stretch space-x-2"
                        >
                            <NavigationMenuItem
                                v-for="item in availableNavItems"
                                :key="item.title"
                                class="relative flex h-full items-center"
                            >
                                <Link
                                    v-if="hasHref(item)"
                                    :class="[
                                        navigationMenuTriggerStyle(),
                                        whenCurrentUrl(
                                            item.href,
                                            activeItemStyles,
                                        ),
                                        'h-9 cursor-pointer px-3',
                                    ]"
                                    :href="item.href"
                                >
                                    <component
                                        v-if="item.icon"
                                        :is="item.icon"
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{ item.title }}
                                </Link>
                                <template v-else-if="item.items">
                                    <NavigationMenuTrigger
                                        :class="[
                                            navigationMenuTriggerStyle(),
                                            isItemActive(item) &&
                                                activeItemStyles,
                                            'h-9 px-3',
                                        ]"
                                    >
                                        <component
                                            v-if="item.icon"
                                            :is="item.icon"
                                            class="mr-2 h-4 w-4"
                                        />
                                        {{ item.title }}
                                    </NavigationMenuTrigger>
                                    <NavigationMenuContent>
                                        <div
                                            class="grid min-w-[280px] gap-1 p-3"
                                        >
                                            <Link
                                                v-for="subItem in item.items"
                                                :key="subItem.title"
                                                :href="
                                                    hasHref(subItem)
                                                        ? subItem.href
                                                        : '#'
                                                "
                                                class="flex items-start gap-3 rounded-md px-3 py-2 text-sm hover:bg-accent"
                                                :class="
                                                    hasHref(subItem) &&
                                                    whenCurrentUrl(
                                                        subItem.href,
                                                        activeItemStyles,
                                                    )
                                                "
                                            >
                                                <component
                                                    v-if="subItem.icon"
                                                    :is="subItem.icon"
                                                    class="mt-0.5 h-4 w-4"
                                                />
                                                <span>{{ subItem.title }}</span>
                                            </Link>
                                        </div>
                                    </NavigationMenuContent>
                                </template>
                                <div
                                    v-if="
                                        hasHref(item) && isCurrentUrl(item.href)
                                    "
                                    class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"
                                ></div>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar
                                    class="size-8 overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div
            v-if="props.breadcrumbs.length > 1"
            class="flex w-full border-b border-sidebar-border/70"
        >
            <div
                class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>
