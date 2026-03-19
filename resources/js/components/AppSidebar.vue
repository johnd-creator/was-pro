<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { appNavigationItems } from '@/lib/app-navigation';
import { dashboard as dashboardRoute } from '@/routes';
import type { NavItemWithRequiredHref } from '@/types';

const footerNavItems: NavItemWithRequiredHref[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="border-b border-sidebar-border/50 pb-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardRoute()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <p
                class="mt-2 px-1 text-[10px] leading-tight font-medium text-sidebar-foreground/70 group-data-[collapsible=icon]:hidden"
            >
                Pusat Operasional Limbah
            </p>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="appNavigationItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
