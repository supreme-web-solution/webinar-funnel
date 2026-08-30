<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

type Props = {
    items: NavItem[];
    class?: string;
};

defineProps<Props>();

function isExternal(href: string): boolean {
    return href.startsWith('http') || href === '#';
}
</script>

<template>
    <SidebarGroup :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        class="h-8 rounded-lg px-3 text-xs text-teal-200/40 hover:bg-sidebar-accent/60 hover:text-teal-100/70 transition-colors"
                        as-child
                    >
                        <Link v-if="!isExternal(item.href)" :href="item.href" class="flex items-center gap-2.5">
                            <Icon v-if="typeof item.icon === 'string'" :icon="item.icon" class="size-3.5 shrink-0" />
                            <span>{{ item.title }}</span>
                        </Link>
                        <a v-else :href="item.href" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2.5">
                            <Icon v-if="typeof item.icon === 'string'" :icon="item.icon" class="size-3.5 shrink-0" />
                            <span>{{ item.title }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
