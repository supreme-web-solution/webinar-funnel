<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel v-if="label" class="text-[0.65rem] font-semibold tracking-widest uppercase text-sidebar-foreground/50 px-2 mb-1">
            {{ label }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="group relative h-9 rounded-lg px-3 text-sm font-medium transition-all duration-150
                           text-sidebar-foreground/75 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground
                           data-[active=true]:bg-sidebar-primary/15 data-[active=true]:text-sidebar-primary"
                >
                    <Link :href="item.href" class="flex items-center gap-3 w-full">
                        <Icon
                            v-if="typeof item.icon === 'string'"
                            :icon="item.icon"
                            class="size-4 shrink-0"
                        />
                        <component
                            :is="item.icon"
                            v-else-if="item.icon"
                            class="size-4 shrink-0"
                        />
                        <span class="truncate">{{ item.title }}</span>
                        <Badge
                            v-if="item.badge !== undefined"
                            class="ml-auto h-5 min-w-[1.25rem] rounded-full bg-sidebar-primary text-sidebar-primary-foreground text-[0.65rem] px-1.5"
                        >
                            {{ item.badge }}
                        </Badge>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
