<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    SidebarSeparator,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

/** Core app navigation — included in FE and Bundle. */
const appNavItems: NavItem[] = [
    {
        title: 'Tutorial',
        href: '/tutorial',
        icon: 'heroicons:academic-cap',
    },
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: 'heroicons:squares-2x2',
    },
    {
        title: 'My Funnels',
        href: '/funnels',
        icon: 'heroicons:funnel',
    },
    {
        title: 'Templates',
        href: '/templates',
        icon: 'heroicons:rectangle-stack',
    },
    {
        title: 'Leads',
        href: '/leads',
        icon: 'heroicons:users',
    },
    {
        title: 'Promo Calendar',
        href: '/promotion/calendar',
        icon: 'heroicons:calendar-days',
    },
    {
        title: 'Integrations',
        href: '/integrations',
        icon: 'heroicons:puzzle-piece',
    },
];

/** Bundle-only navigation — add new items here when they ship. */
const bundleNavItems: NavItem[] = [];

const page = usePage<{
    auth?: {
        is_admin?: boolean;
        can_view_app_features?: boolean;
        can_view_bundle_features?: boolean;
    };
}>();

const navItems = computed<NavItem[]>(() => {
    const canViewApp = page.props.auth?.can_view_app_features ?? true;
    const canViewBundle = page.props.auth?.can_view_bundle_features ?? false;

    const items: NavItem[] = canViewApp ? [...appNavItems] : [];

    if (canViewBundle) {
        items.push(...bundleNavItems);
    }

    if (page.props.auth?.is_admin) {
        items.push({
            title: 'Users',
            href: '/users',
            icon: 'heroicons:user-group',
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Changelog',
        href: '#',
        icon: 'heroicons:bolt',
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <!-- Brand header -->
        <SidebarHeader class="border-b border-sidebar-border/70 pb-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="rounded-xl border border-sidebar-border/70 bg-white/80 shadow-sm hover:bg-white transition-colors">
                        <Link href="/dashboard" class="flex items-center gap-3 px-1">
                            <!-- Logo mark -->
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-linear-to-br from-emerald-500 to-cyan-500 shadow-sm">
                                <Icon icon="heroicons:video-camera" class="size-4.5 text-white" />
                            </div>
                            <div class="grid flex-1 text-left leading-tight">
                                <span class="truncate text-[0.8rem] font-bold tracking-tight text-sidebar-accent-foreground">
                                    AffiliMachine Ai
                                </span>
                                <span class="truncate text-[0.65rem] text-sidebar-foreground/60">
                                    Affiliate Business Builder
                                </span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <!-- Main nav -->
        <SidebarContent class="mt-2 gap-0">
            <NavMain :items="navItems" label="Navigation" />
        </SidebarContent>

        <!-- Footer -->
        <SidebarFooter class="border-t border-sidebar-border/70 bg-white/35 pt-2">
            <NavFooter :items="footerNavItems" />
            <SidebarSeparator class="my-1 bg-sidebar-border/70" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
