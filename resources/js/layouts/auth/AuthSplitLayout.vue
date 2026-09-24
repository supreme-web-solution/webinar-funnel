<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useAppName } from '@/composables/useAppName';
import { home } from '@/routes';

const appName = useAppName();

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();

const marketers = [
    { initials: 'JK', class: 'from-blue-400 to-blue-700' },
    { initials: 'SM', class: 'from-blue-400 to-blue-600' },
    { initials: 'DR', class: 'from-blue-400 to-blue-700' },
    { initials: 'AL', class: 'from-blue-500 to-blue-700' },
    { initials: 'MT', class: 'from-blue-300 to-blue-600' },
];

const features = computed(() => {
    const items = [
        { icon: 'heroicons:cursor-arrow-rays', label: 'Traffic Engine' },
        { icon: 'heroicons:users', label: 'Lead Generation' },
        { icon: 'heroicons:video-camera', label: 'Webinar Sales' },
        { icon: 'heroicons:rectangle-stack', label: 'Affiliate Funnels' },
        { icon: 'heroicons:megaphone', label: 'Social Promotion' },
    ];

    if (page.props.paidAdsEnabled === true) {
        items.push({ icon: 'heroicons:chart-bar', label: 'Paid Ads' });
    }

    return items;
});
</script>

<template>
    <div class="relative min-h-svh overflow-hidden bg-background">
        <!-- Soft dashboard-style backdrop -->
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-24 top-0 size-[420px] rounded-full bg-blue-400/10 blur-3xl" />
            <div class="absolute -right-24 bottom-0 size-[380px] rounded-full bg-blue-500/8 blur-3xl" />
            <div
                class="absolute inset-0 opacity-[0.35]"
                style="background-image: radial-gradient(circle at 1px 1px, hsl(214 32% 88%) 1px, transparent 0); background-size: 28px 28px;"
            />
        </div>

        <div class="relative flex min-h-svh flex-col items-center justify-center px-4 py-10 sm:px-6">
            <div class="mb-6 flex w-full max-w-md flex-col items-center text-center">
                <Link
                    :href="home()"
                    class="mb-4 flex items-center gap-2.5 rounded-xl transition-opacity hover:opacity-90"
                >
                    <img
                        src="/favicon.png"
                        :alt="appName"
                        class="size-10 rounded-xl shadow-sm ring-1 ring-blue-500/25"
                    />
                    <div class="text-left">
                        <p class="text-sm font-bold tracking-tight text-foreground">{{ appName }}</p>
                        <p class="text-[0.65rem] text-muted-foreground">Affiliate Business Builder</p>
                    </div>
                </Link>

                <div class="inline-flex items-center gap-2 rounded-full border border-blue-200/80 bg-white/80 px-3 py-1 shadow-sm backdrop-blur-sm">
                    <span class="size-1.5 animate-pulse rounded-full bg-blue-500" />
                    <span class="text-[0.65rem] font-semibold uppercase tracking-widest text-blue-700">
                        All-in-one affiliate OS
                    </span>
                </div>
            </div>

            <!-- Form card -->
            <div class="w-full max-w-md rounded-2xl border border-border/70 bg-white p-6 shadow-[0_8px_30px_rgba(15,23,42,0.06)] sm:p-8">
                <div class="mb-6 space-y-1.5 text-center">
                    <h1 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">{{ title }}</h1>
                    <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
                </div>

                <slot />
            </div>

            <!-- Social proof + features -->
            <div class="mt-8 w-full max-w-md space-y-5">
                <div class="flex flex-col items-center gap-3 rounded-2xl border border-border/60 bg-white/70 px-5 py-4 shadow-sm backdrop-blur-sm">
                    <div class="flex -space-x-2.5">
                        <Avatar
                            v-for="(member, index) in marketers"
                            :key="member.initials"
                            class="size-9 ring-2 ring-white"
                            :style="{ zIndex: marketers.length - index }"
                        >
                            <AvatarFallback
                                :class="['bg-linear-to-br text-[10px] font-bold text-white', member.class]"
                            >
                                {{ member.initials }}
                            </AvatarFallback>
                        </Avatar>
                    </div>
                    <p class="text-center text-xs leading-relaxed text-muted-foreground">
                        Join <span class="font-semibold text-foreground">2,400+ marketers</span> building
                        traffic, leads &amp; commissions with AI
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2">
                    <div
                        v-for="item in features"
                        :key="item.label"
                        class="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-white/80 px-2.5 py-1 text-[0.65rem] font-medium text-muted-foreground shadow-sm"
                    >
                        <Icon :icon="item.icon" class="size-3.5 text-blue-600" />
                        {{ item.label }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
