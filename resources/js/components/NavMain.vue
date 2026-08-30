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
    <SidebarGroup class="px-2 py-0.5">
        <SidebarGroupLabel
            v-if="label"
            class="mb-0.5 px-2 text-[0.6rem] font-semibold tracking-[0.14em] uppercase text-teal-200/40"
        >
            {{ label }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="group relative h-9 rounded-xl border border-transparent px-2.5 text-[0.8125rem] font-medium transition-all duration-150
                           text-sidebar-foreground/75 hover:border-teal-500/15 hover:bg-sidebar-accent/80 hover:text-teal-50
                           data-[active=true]:sidebar-nav-active data-[active=true]:text-teal-50"
                >
                    <Link :href="item.href" class="flex w-full items-center gap-2.5">
                        <span
                            v-if="isCurrentUrl(item.href)"
                            class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-teal-400"
                        />
                        <Icon
                            v-if="typeof item.icon === 'string'"
                            :icon="item.icon"
                            class="size-[1.05rem] shrink-0 text-teal-200/50 transition-colors group-hover:text-teal-300/80 group-data-[active=true]:text-teal-300"
                        />
                        <component
                            :is="item.icon"
                            v-else-if="item.icon"
                            class="size-[1.05rem] shrink-0 text-teal-200/50"
                        />
                        <span class="truncate">{{ item.title }}</span>
                        <Badge
                            v-if="item.badge !== undefined"
                            class="ml-auto h-5 min-w-5 rounded-full border-teal-400/20 bg-teal-600/40 px-1.5 text-[0.65rem] text-teal-50"
                        >
                            {{ item.badge }}
                        </Badge>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
