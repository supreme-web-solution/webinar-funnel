<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-10 flex h-14 shrink-0 items-center justify-between gap-4 border-b border-border/60 bg-background/95 backdrop-blur-sm px-4 md:px-6 transition-[height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <!-- Left: trigger + breadcrumbs -->
        <div class="flex items-center gap-3 min-w-0">
            <SidebarTrigger
                class="-ml-1 size-8 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
            />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="hidden sm:block h-4 w-px bg-border/60" />
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <!-- Right: action area -->
        <div class="flex items-center gap-2 shrink-0">
            <Link
                href="/funnels/create"
                class="hidden md:flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-sm hover:opacity-90 transition-opacity"
                style="background:#40E0D0; color:#0f172a"
            >
                <Icon icon="heroicons:plus" class="size-3.5" />
                New Funnel
            </Link>
            <Link
                href="/integrations/coaching"
                class="hidden md:flex items-center gap-1.5 rounded-lg border border-emerald-200/80 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-100 transition-colors"
            >
                <Icon icon="heroicons:user-group" class="size-3.5" />
                1-on-1 Coaching
            </Link>
            <a
                href="#"
                class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                title="Notifications"
            >
                <Icon icon="heroicons:bell" class="size-4.5" />
            </a>
        </div>
    </header>
</template>
