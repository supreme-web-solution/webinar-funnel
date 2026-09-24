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
            class="mb-0.5 px-2 text-[0.6rem] font-semibold tracking-[0.14em] uppercase text-blue-200/35"
        >
            {{ label }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="group relative h-9 rounded-[10px] border border-transparent px-2.5 text-[0.8125rem] font-medium transition-all duration-150
                           text-sidebar-foreground/80 hover:border-blue-500/10 hover:bg-sidebar-accent hover:text-blue-50
                           data-[active=true]:sidebar-nav-active data-[active=true]:text-blue-50"
                >
                    <Link :href="item.href" class="flex w-full items-center gap-2.5">
                        <span
                            v-if="isCurrentUrl(item.href)"
                            class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-[#60A5FA]"
                        />
                        <Icon
                            v-if="typeof item.icon === 'string'"
                            :icon="item.icon"
                            class="size-[1.05rem] shrink-0 text-blue-200/45 transition-colors group-hover:text-blue-200/75 group-data-[active=true]:text-[#93C5FD]"
                        />
                        <component
                            :is="item.icon"
                            v-else-if="item.icon"
                            class="size-[1.05rem] shrink-0 text-blue-200/45"
                        />
                        <span class="truncate">{{ item.title }}</span>
                        <Badge
                            v-if="item.badge !== undefined"
                            class="ml-auto h-5 min-w-5 rounded-full border-blue-400/25 bg-[#2563EB]/35 px-1.5 text-[0.65rem] text-blue-50"
                        >
                            {{ item.badge }}
                        </Badge>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
