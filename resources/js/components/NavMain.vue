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
    <SidebarGroup class="px-2 py-1">
        <SidebarGroupLabel v-if="label" class="mb-1 px-2 text-[0.65rem] font-semibold tracking-widest uppercase text-sidebar-foreground/55">
            {{ label }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="group relative h-10 rounded-xl border border-transparent px-3 text-sm font-medium transition-all duration-150
                           text-sidebar-foreground/80 hover:border-sidebar-border hover:bg-white/90 hover:text-sidebar-accent-foreground hover:shadow-sm
                           data-[active=true]:border-emerald-200 data-[active=true]:bg-linear-to-r data-[active=true]:from-emerald-100/90 data-[active=true]:to-cyan-100/80
                           data-[active=true]:text-emerald-700 data-[active=true]:shadow-sm"
                >
                    <Link :href="item.href" class="flex items-center gap-3 w-full">
                        <Icon
                            v-if="typeof item.icon === 'string'"
                            :icon="item.icon"
                            class="size-4 shrink-0 text-sidebar-foreground/65 transition-colors group-data-[active=true]:text-emerald-600"
                        />
                        <component
                            :is="item.icon"
                            v-else-if="item.icon"
                            class="size-4 shrink-0 text-sidebar-foreground/65 transition-colors group-data-[active=true]:text-emerald-600"
                        />
                        <span class="truncate">{{ item.title }}</span>
                        <Badge
                            v-if="item.badge !== undefined"
                            class="ml-auto h-5 min-w-5 rounded-full bg-emerald-500 px-1.5 text-[0.65rem] text-white"
                        >
                            {{ item.badge }}
                        </Badge>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
