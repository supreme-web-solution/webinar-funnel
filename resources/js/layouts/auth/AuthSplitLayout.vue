<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();

const allCapabilities = [
    { icon: 'heroicons:cursor-arrow-rays', label: 'Traffic Engine', detail: 'AI-driven traffic 24/7' },
    { icon: 'heroicons:users', label: 'Lead Generation', detail: 'Capture & nurture leads' },
    { icon: 'heroicons:video-camera', label: 'Webinar Sales Machine', detail: 'Automated evergreen sales' },
    { icon: 'heroicons:rectangle-stack', label: 'Affiliate Funnels', detail: '51+ proven templates' },
    { icon: 'heroicons:megaphone', label: 'Social Promotion', detail: 'Posts, images & video' },
    { icon: 'heroicons:chart-bar', label: 'Paid Ads', detail: 'Meta, Google & more' },
];

const capabilities = computed(() => {
    if (page.props.paidAdsEnabled === true) {
        return allCapabilities;
    }

    return allCapabilities.filter((item) => item.label !== 'Paid Ads');
});
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-2">

        <!-- ── Left panel — platform overview ── -->
        <div class="relative hidden overflow-hidden border-r border-primary/20 bg-linear-to-br from-[#071821] via-[#0b2230] to-[#0f172a] lg:flex lg:flex-col">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -left-32 -top-32 size-[520px] rounded-full bg-primary/15 blur-[120px]" />
                <div class="absolute bottom-0 right-0 size-[400px] rounded-full bg-primary/10 blur-[100px]" />
            </div>

            <div class="relative z-10 flex flex-col h-full p-10">

                <Link :href="home()" class="flex w-fit items-center gap-2.5">
                    <img
                        src="/favicon.png"
                        alt="AffiliMachine Ai"
                        class="size-9 rounded-xl shadow-sm ring-1 ring-primary/30"
                    />
                    <span class="text-sm font-bold tracking-tight text-white">AffiliMachine Ai</span>
                </Link>

                <div class="my-auto">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary/35 bg-primary/10 px-3 py-1 shadow-sm">
                        <span class="size-1.5 animate-pulse rounded-full bg-primary" />
                        <span class="text-[0.7rem] font-semibold tracking-widest text-primary uppercase">
                            All-in-one Affiliate Business Builder
                        </span>
                    </div>

                    <h1 class="mb-3 text-4xl leading-tight font-extrabold text-white">
                        The world's first AI that builds your entire affiliate
                        <span class="text-primary">business</span>
                    </h1>
                    <p class="mb-8 max-w-md text-sm leading-relaxed text-slate-300">
                        Automatically creates your Traffic Engine, Lead Generation System &amp; Automated
                        Webinar Sales Machine — all working together as ONE complete affiliate business
                        that generates commissions for you around the clock.
                    </p>

                    <div class="rounded-2xl border border-primary/20 bg-white/75 p-5 shadow-sm backdrop-blur-sm">
                        <p class="mb-4 text-[10px] font-semibold tracking-widest text-primary uppercase">
                            Three engines. One business.
                        </p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div
                                v-for="item in capabilities"
                                :key="item.label"
                                class="rounded-xl border border-primary/15 bg-white p-3 shadow-sm"
                            >
                                <div class="mb-2 inline-flex size-7 items-center justify-center rounded-lg bg-primary/10">
                                    <Icon :icon="item.icon" class="size-4 text-primary" />
                                </div>
                                <p class="text-[11px] font-semibold text-slate-800">{{ item.label }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">{{ item.detail }}</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-primary/20 bg-primary/5 p-3">
                            <p class="text-[10px] font-semibold tracking-wide text-primary uppercase">How it works</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-700">
                                <span class="font-medium">Activate your business</span>
                                <Icon icon="heroicons:arrow-right" class="size-3.5 text-primary" />
                                <span class="font-medium">AI builds traffic &amp; leads</span>
                                <Icon icon="heroicons:arrow-right" class="size-3.5 text-primary" />
                                <span class="font-medium">Earn commissions 24/7</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 p-4 shadow-sm backdrop-blur-sm">
                    <div class="flex -space-x-2">
                        <div
                            v-for="i in 4"
                            :key="i"
                            class="size-7 rounded-full border-2 border-white bg-linear-to-br from-primary to-[#23B896]"
                        />
                    </div>
                    <p class="text-xs leading-snug text-slate-300">
                        <span class="font-semibold text-white">2,400+</span> marketers already using AffiliMachine Ai
                    </p>
                </div>
            </div>
        </div>

        <!-- ── Right panel — form ── -->
        <div class="flex flex-col items-center justify-center bg-linear-to-b from-white to-primary/5 px-6 py-10 sm:px-10">

            <Link :href="home()" class="mb-8 flex items-center gap-2 lg:hidden">
                <img
                    src="/favicon.png"
                    alt="AffiliMachine Ai"
                    class="size-8 rounded-lg ring-1 ring-primary/25"
                />
                <span class="text-sm font-bold tracking-tight text-foreground">AffiliMachine Ai</span>
            </Link>

            <div class="w-full max-w-sm">
                <div class="mb-8 space-y-1.5 text-center lg:text-left">
                    <h2 class="text-xl font-bold tracking-tight text-foreground">{{ title }}</h2>
                    <p class="text-sm text-muted-foreground">{{ description }}</p>
                </div>

                <slot />
            </div>
        </div>

    </div>
</template>
