<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

type Props = {
    items: NavItem[];
    class?: string;
};

defineProps<Props>();
</script>

<template>
    <SidebarGroup :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        class="h-8 rounded-lg px-3 text-xs text-sidebar-foreground/50 hover:text-sidebar-foreground/80 hover:bg-sidebar-accent transition-colors"
                        as-child
                    >
                        <a :href="toUrl(item.href)" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2.5">
                            <Icon
                                v-if="typeof item.icon === 'string'"
                                :icon="item.icon"
                                class="size-3.5 shrink-0"
                            />
                            <component
                                :is="item.icon"
                                v-else-if="item.icon"
                                class="size-3.5 shrink-0"
                            />
                            <span>{{ item.title }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
