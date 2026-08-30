<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
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
        class="sticky top-0 z-10 flex h-14 shrink-0 items-center justify-between gap-4 border-b border-border/50 bg-white/80 px-4 backdrop-blur-md md:px-6 transition-[height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <div class="flex min-w-0 items-center gap-3">
            <SidebarTrigger
                class="-ml-1 size-8 rounded-xl text-muted-foreground transition-colors hover:bg-teal-50 hover:text-teal-800"
            />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <div class="hidden h-4 w-px bg-border/60 sm:block" />
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <Button as-child variant="brand-outline" size="sm" class="hidden md:inline-flex">
                <Link href="/integrations/coaching">
                    <Icon icon="heroicons:user-group" class="size-3.5" />
                    Support
                </Link>
            </Button>
            <Button as-child variant="brand" size="sm">
                <Link href="/campaigns/create">
                    <Icon icon="heroicons:plus" class="size-3.5" />
                    New Campaign
                </Link>
            </Button>
            <button
                type="button"
                class="flex size-8 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-teal-50 hover:text-teal-800"
                title="Notifications"
            >
                <Icon icon="heroicons:bell" class="size-4" />
            </button>
        </div>
    </header>
</template>
