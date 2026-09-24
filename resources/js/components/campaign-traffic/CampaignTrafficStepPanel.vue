<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    trafficHubUrl: string;
    paidAdsEnabled?: boolean;
}>();

const cards = computed(() => {
    const base = props.trafficHubUrl.replace(/\/$/, '');
    const items = [
        { label: 'Overview', desc: 'Keywords, posts & ads at a glance', href: base, icon: 'heroicons:squares-2x2', color: 'bg-violet-100 text-violet-700' },
        { label: 'Free traffic', desc: 'Reddit, YouTube, X & news mentions', href: `${base}/free`, icon: 'heroicons:magnifying-glass-circle', color: 'bg-sky-100 text-sky-700' },
        { label: 'Social posts', desc: 'Scripts, scheduling & publishing', href: `${base}/promotion/posts`, icon: 'heroicons:megaphone', color: 'bg-indigo-100 text-indigo-700' },
        { label: 'Promo calendar', desc: 'Drag-and-drop content calendar', href: `${base}/promotion/calendar`, icon: 'heroicons:calendar-days', color: 'bg-amber-100 text-amber-700' },
    ];
    if (props.paidAdsEnabled) {
        items.push({
            label: 'Paid ads',
            desc: 'Facebook & Meta via Zernio',
            href: `${base}/ads`,
            icon: 'heroicons:currency-dollar',
            color: 'bg-blue-100 text-blue-700',
        });
    }

    return items;
});
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
            Each campaign gets its own traffic workspace — separate from the webinar room. Open any section below to manage discovery, promotion, and ads.
        </p>
        <div class="grid gap-3 sm:grid-cols-2">
            <Link
                v-for="card in cards"
                :key="card.href"
                :href="card.href"
                class="group rounded-xl border bg-card p-4 transition-colors hover:border-violet-200 hover:bg-violet-50/30"
            >
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg" :class="card.color">
                        <Icon :icon="card.icon" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold group-hover:text-violet-700">{{ card.label }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ card.desc }}</p>
                    </div>
                    <Icon icon="heroicons:arrow-right" class="ml-auto size-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                </div>
            </Link>
        </div>
        <Button as-child variant="outline" size="sm">
            <Link :href="trafficHubUrl">
                <Icon icon="heroicons:signal" class="mr-1.5 size-4" />
                Open traffic hub
            </Link>
        </Button>
    </div>
</template>
