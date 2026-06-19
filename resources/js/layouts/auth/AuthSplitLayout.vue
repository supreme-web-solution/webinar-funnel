<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const features = [
    { icon: 'heroicons:rocket-launch', text: '50 done-for-you webinar funnel templates' },
    { icon: 'heroicons:envelope', text: 'Auto-subscribe leads to 10+ email platforms' },
    { icon: 'heroicons:chat-bubble-left-right', text: 'Live webinar chat with simulated engagement' },
    { icon: 'heroicons:share', text: 'Instant shareable public funnel links' },
];
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-2">

        <!-- ── Left panel — funnel flow visual ── -->
        <div class="relative hidden overflow-hidden border-r border-emerald-900/40 bg-linear-to-br from-[#071821] via-[#0b2230] to-[#0f172a] lg:flex lg:flex-col">
            <!-- Gradient blobs -->
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -left-32 -top-32 size-[520px] rounded-full bg-emerald-400/18 blur-[120px]" />
                <div class="absolute bottom-0 right-0 size-[400px] rounded-full bg-cyan-400/16 blur-[100px]" />
            </div>

            <!-- Content -->
            <div class="relative z-10 flex flex-col h-full p-10">

                <!-- Logo -->
                <Link :href="home()" class="flex w-fit items-center gap-2.5">
                    <div class="flex size-9 items-center justify-center rounded-xl border border-emerald-300/35 bg-white/10 shadow-sm">
                        <Icon icon="heroicons:video-camera" class="size-5 text-emerald-600" />
                    </div>
                    <span class="text-sm font-bold tracking-tight text-white">WebinarFlow Ai</span>
                </Link>

                <!-- Hero copy -->
                <div class="my-auto">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-300/35 bg-emerald-400/10 px-3 py-1 shadow-sm">
                        <span class="size-1.5 animate-pulse rounded-full bg-emerald-500" />
                        <span class="text-[0.7rem] font-semibold tracking-widest text-emerald-300 uppercase">
                            Webinar Funnel Builder
                        </span>
                    </div>

                    <h1 class="mb-3 text-4xl leading-tight font-extrabold text-white">
                        Build your webinar
                        <span class="text-emerald-600">flow</span>
                    </h1>
                    <p class="mb-8 max-w-md text-sm leading-relaxed text-slate-300">
                        Design, connect, and launch your funnel like a visual automation pipeline.
                    </p>

                    <!-- Funnel flow board -->
                    <div class="relative rounded-2xl border border-emerald-200/80 bg-white/75 p-5 shadow-sm backdrop-blur-sm">
                        <div class="absolute left-6 right-6 top-1/2 h-px -translate-y-1/2 border-t border-dashed border-emerald-200"></div>

                        <div class="relative z-10 grid grid-cols-4 gap-3">
                            <div class="rounded-xl border border-emerald-200 bg-white p-3 shadow-sm">
                                <div class="mb-2 inline-flex size-7 items-center justify-center rounded-lg bg-emerald-50">
                                    <Icon icon="heroicons:rectangle-stack" class="size-4 text-emerald-600" />
                                </div>
                                <p class="text-[11px] font-semibold text-slate-800">Template</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">Pick starter</p>
                            </div>

                            <div class="rounded-xl border border-cyan-200 bg-white p-3 shadow-sm">
                                <div class="mb-2 inline-flex size-7 items-center justify-center rounded-lg bg-cyan-50">
                                    <Icon icon="heroicons:pencil-square" class="size-4 text-cyan-600" />
                                </div>
                                <p class="text-[11px] font-semibold text-slate-800">Opt-in Page</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">Edit content</p>
                            </div>

                            <div class="rounded-xl border border-violet-200 bg-white p-3 shadow-sm">
                                <div class="mb-2 inline-flex size-7 items-center justify-center rounded-lg bg-violet-50">
                                    <Icon icon="heroicons:puzzle-piece" class="size-4 text-violet-600" />
                                </div>
                                <p class="text-[11px] font-semibold text-slate-800">Integrations</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">Connect ESP</p>
                            </div>

                            <div class="rounded-xl border border-amber-200 bg-white p-3 shadow-sm">
                                <div class="mb-2 inline-flex size-7 items-center justify-center rounded-lg bg-amber-50">
                                    <Icon icon="heroicons:rocket-launch" class="size-4 text-amber-600" />
                                </div>
                                <p class="text-[11px] font-semibold text-slate-800">Publish</p>
                                <p class="mt-0.5 text-[10px] text-slate-500">Go live fast</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/60 p-3">
                                <p class="text-[10px] font-semibold tracking-wide text-emerald-700 uppercase">Live Path</p>
                                <div class="mt-1 flex items-center gap-1.5 text-[11px] text-slate-700">
                                    <Icon icon="heroicons:arrow-right" class="size-3.5 text-emerald-600" />
                                    Opt-in
                                    <Icon icon="heroicons:arrow-right" class="size-3.5 text-emerald-600" />
                                    Webinar
                                    <Icon icon="heroicons:arrow-right" class="size-3.5 text-emerald-600" />
                                    Offer
                                </div>
                            </div>
                            <div class="rounded-xl border border-cyan-200/80 bg-cyan-50/60 p-3">
                                <p class="text-[10px] font-semibold tracking-wide text-cyan-700 uppercase">Automation</p>
                                <div class="mt-1 flex items-center gap-1.5 text-[11px] text-slate-700">
                                    <Icon icon="heroicons:user-plus" class="size-3.5 text-cyan-600" />
                                    Capture lead
                                    <Icon icon="heroicons:arrow-right" class="size-3.5 text-cyan-600" />
                                    Send to ESP
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social proof strip -->
                <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 p-4 shadow-sm backdrop-blur-sm">
                    <div class="flex -space-x-2">
                        <div v-for="i in 4" :key="i" class="size-7 rounded-full border-2 border-white bg-linear-to-br from-emerald-300 to-cyan-300" />
                    </div>
                    <p class="text-xs leading-snug text-slate-300">
                        <span class="font-semibold text-white">2,400+</span> marketers already using WebinarFlow Ai
                    </p>
                </div>
            </div>
        </div>

        <!-- ── Right panel — form ── -->
        <div class="flex flex-col items-center justify-center bg-linear-to-b from-white to-emerald-50/35 px-6 py-10 sm:px-10">

            <!-- Mobile logo (hidden on desktop) -->
            <Link :href="home()" class="flex items-center gap-2 mb-8 lg:hidden">
                <div class="flex size-8 items-center justify-center rounded-lg bg-primary/10 border border-primary/25">
                    <Icon icon="heroicons:video-camera" class="size-4 text-primary" />
                </div>
                <span class="font-bold text-sm tracking-tight text-foreground">WebinarFlow Ai</span>
            </Link>

            <div class="w-full max-w-sm">
                <!-- Heading -->
                <div class="mb-8 space-y-1.5 text-center lg:text-left">
                    <h2 class="text-xl font-bold text-foreground tracking-tight">{{ title }}</h2>
                    <p class="text-sm text-muted-foreground">{{ description }}</p>
                </div>

                <!-- Form slot -->
                <slot />
            </div>
        </div>

    </div>
</template>
