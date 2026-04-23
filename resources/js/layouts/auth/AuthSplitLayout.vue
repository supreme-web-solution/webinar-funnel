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
    <div class="min-h-screen grid lg:grid-cols-2">

        <!-- ── Left panel — brand ── -->
        <div class="relative hidden lg:flex flex-col overflow-hidden bg-[#060d1a]">
            <!-- Gradient blobs -->
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-32 -left-32 size-[520px] rounded-full bg-[#40E0D0]/10 blur-[120px]" />
                <div class="absolute bottom-0 right-0 size-[400px] rounded-full bg-[#FFAD00]/8 blur-[100px]" />
            </div>

            <!-- Content -->
            <div class="relative z-10 flex flex-col h-full p-10">

                <!-- Logo -->
                <Link :href="home()" class="flex items-center gap-2.5 w-fit">
                    <div class="flex size-9 items-center justify-center rounded-xl bg-[#40E0D0]/15 border border-[#40E0D0]/30">
                        <Icon icon="heroicons:video-camera" class="size-5 text-[#40E0D0]" />
                    </div>
                    <span class="font-bold text-white text-sm tracking-tight">DFY Webinar Forge</span>
                </Link>

                <!-- Hero copy -->
                <div class="my-auto">
                    <div class="inline-flex items-center gap-2 rounded-full border border-[#40E0D0]/25 bg-[#40E0D0]/10 px-3 py-1 mb-6">
                        <span class="size-1.5 rounded-full bg-[#40E0D0] animate-pulse" />
                        <span class="text-[0.7rem] font-semibold text-[#40E0D0] tracking-widest uppercase">
                            Webinar Funnel Builder
                        </span>
                    </div>

                    <h1 class="text-4xl font-extrabold text-white leading-tight mb-4">
                        Launch your webinar<br />
                        funnel in
                        <span class="text-[#40E0D0]"> minutes.</span>
                    </h1>
                    <p class="text-white/50 text-sm leading-relaxed mb-10 max-w-sm">
                        Select a template, connect your ESP, and get a shareable opt-in link — fully set up with a live webinar room and chat.
                    </p>

                    <!-- Feature list -->
                    <ul class="space-y-3.5">
                        <li
                            v-for="f in features"
                            :key="f.text"
                            class="flex items-start gap-3"
                        >
                            <div class="flex size-7 items-center justify-center rounded-lg bg-[#40E0D0]/10 border border-[#40E0D0]/20 shrink-0 mt-0.5">
                                <Icon :icon="f.icon" class="size-3.5 text-[#40E0D0]" />
                            </div>
                            <span class="text-sm text-white/70 leading-snug">{{ f.text }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Social proof strip -->
                <div class="flex items-center gap-3 rounded-xl border border-white/8 bg-white/4 p-4 backdrop-blur-sm">
                    <div class="flex -space-x-2">
                        <div v-for="i in 4" :key="i" class="size-7 rounded-full border-2 border-[#060d1a] bg-gradient-to-br from-[#40E0D0]/60 to-[#FFAD00]/60" />
                    </div>
                    <p class="text-xs text-white/60 leading-snug">
                        <span class="font-semibold text-white">2,400+</span> marketers already using DFY Webinar Forge
                    </p>
                </div>
            </div>
        </div>

        <!-- ── Right panel — form ── -->
        <div class="flex flex-col items-center justify-center bg-background px-6 py-10 sm:px-10">

            <!-- Mobile logo (hidden on desktop) -->
            <Link :href="home()" class="flex items-center gap-2 mb-8 lg:hidden">
                <div class="flex size-8 items-center justify-center rounded-lg bg-primary/10 border border-primary/25">
                    <Icon icon="heroicons:video-camera" class="size-4 text-primary" />
                </div>
                <span class="font-bold text-sm tracking-tight text-foreground">DFY Webinar Forge</span>
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
