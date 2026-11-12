<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import Link from '@/components/OrganizationLink.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    appFooterNavigationItems,
    appNavigationItems,
} from '@/lib/app-navigation';
import { dashboard as dashboardRoute } from '@/routes';
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="border-none bg-sidebar text-sidebar-foreground shadow-2xl shadow-slate-950/10"
    >
        <SidebarHeader class="border-b border-sidebar-border/70 pb-3">
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
                class="mt-2 px-1 text-[10px] leading-tight font-semibold text-sidebar-foreground/70 group-data-[collapsible=icon]:hidden"
            >
                Pusat Operasional Limbah
            </p>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="appNavigationItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavMain :items="appFooterNavigationItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
