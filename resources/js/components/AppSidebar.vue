<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
import { useAppName } from '@/composables/useAppName';
import type { NavItem } from '@/types';

type NavGroup = { label: string; items: NavItem[] };

const appName = useAppName();

const mainNav: NavItem[] = [
    { title: 'Dashboard', href: '/dashboard', icon: 'heroicons:squares-2x2' },
    { title: 'Command Center', href: '/command-center', icon: 'heroicons:chat-bubble-left-right' },
    { title: 'Campaigns', href: '/campaigns', icon: 'heroicons:rocket-launch' },
    { title: 'New Campaign', href: '/campaigns/create', icon: 'heroicons:plus-circle' },
];

const assetsNav: NavItem[] = [
    { title: 'Bonus Library', href: '/bonuses', icon: 'heroicons:gift' },
    { title: 'Tracked Links', href: '/tracked-links', icon: 'heroicons:shield-check' },
];

const funnelNav: NavItem[] = [
    { title: 'Webinars', href: '/funnels', icon: 'heroicons:video-camera' },
    { title: 'Leads', href: '/leads', icon: 'heroicons:users' },
    { title: 'Traffic Hub', href: '/traffic', icon: 'heroicons:signal' },
];

const growthNav: NavItem[] = [
    { title: 'Opportunities', href: '/growth/opportunities', icon: 'heroicons:light-bulb' },
    { title: 'Domains', href: '/growth/domains', icon: 'heroicons:globe-alt' },
    { title: 'Content Employee', href: '/growth/content-employee', icon: 'heroicons:sparkles' },
];

const connectNav: NavItem[] = [
    { title: 'Integrations', href: '/integrations', icon: 'heroicons:puzzle-piece' },
    { title: 'Tutorial', href: '/tutorial', icon: 'heroicons:academic-cap' },
];

const page = usePage<{
    auth?: {
        is_admin?: boolean;
        can_view_app_features?: boolean;
        can_view_bundle_features?: boolean;
    };
}>();

const navGroups = computed<NavGroup[]>(() => {
    const canViewApp = page.props.auth?.can_view_app_features ?? true;
    if (!canViewApp) return [];

    const groups: NavGroup[] = [
        { label: 'Main', items: mainNav },
        { label: 'Assets', items: assetsNav },
        { label: 'Funnels & Leads', items: funnelNav },
        { label: 'Growth', items: growthNav },
        { label: 'Connect', items: connectNav },
    ];

    if (page.props.auth?.is_admin) {
        groups.push({
            label: 'Admin',
            items: [{ title: 'Users', href: '/users', icon: 'heroicons:user-group' }],
        });
    }

    return groups;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r border-sidebar-border bg-sidebar text-sidebar-foreground">
        <SidebarHeader class="border-b border-sidebar-border/80 px-2 pb-3 pt-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="rounded-[10px] bg-transparent hover:bg-sidebar-accent/80">
                        <Link href="/dashboard" class="flex items-center gap-3 px-1">
                            <div
                                class="bg-brand-gradient flex size-9 shrink-0 items-center justify-center rounded-[10px] shadow-[var(--brand-gradient-shadow)]"
                            >
                                <Icon icon="heroicons:rocket-launch" class="size-4.5 text-white" />
                            </div>
                            <div class="grid flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                                <span class="truncate text-[0.85rem] font-bold tracking-tight text-blue-50">
                                    {{ appName }}
                                </span>
                                <span class="truncate text-[0.65rem] text-blue-200/55">
                                    AI Affiliate Employee
                                </span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="mt-1 gap-0 overflow-y-auto">
            <NavMain
                v-for="group in navGroups"
                :key="group.label"
                :items="group.items"
                :label="group.label"
            />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/80 bg-sidebar-accent/40 pt-2">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
