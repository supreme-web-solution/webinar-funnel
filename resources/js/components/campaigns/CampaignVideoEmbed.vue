<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    url?: string | null;
    title?: string | null;
    class?: string;
}>();

type VideoKind = 'youtube' | 'vimeo' | 'mp4' | null;

function detectKind(raw: string): VideoKind {
    const u = raw.trim().toLowerCase();
    if (!u) return null;
    if (u.includes('youtube.com') || u.includes('youtu.be')) return 'youtube';
    if (u.includes('vimeo.com')) return 'vimeo';
    if (/\.(mp4|webm|ogg)(\?|$)/i.test(u) || u.startsWith('blob:') || u.includes('/storage/')) return 'mp4';

    return 'mp4';
}

function youtubeId(raw: string): string | null {
    try {
        const u = new URL(raw.includes('://') ? raw : `https://${raw}`);
        if (u.hostname.includes('youtu.be')) {
            return u.pathname.replace('/', '') || null;
        }
        return u.searchParams.get('v') ?? u.pathname.split('/').pop() ?? null;
    } catch {
        return null;
    }
}

function vimeoId(raw: string): string | null {
    const match = raw.match(/vimeo\.com\/(?:video\/)?(\d+)/i);

    return match?.[1] ?? null;
}

const embed = computed(() => {
    const raw = props.url?.trim();
    if (!raw) return null;

    const kind = detectKind(raw);
    if (kind === 'youtube') {
        const id = youtubeId(raw);
        if (!id) return null;

        return { kind, src: `https://www.youtube-nocookie.com/embed/${id}?rel=0` };
    }
    if (kind === 'vimeo') {
        const id = vimeoId(raw);
        if (!id) return null;

        return { kind, src: `https://player.vimeo.com/video/${id}` };
    }

    return { kind: 'mp4' as const, src: raw };
});
</script>

<template>
    <div v-if="embed" class="space-y-2" :class="props.class">
        <p v-if="title" class="text-sm font-semibold text-slate-200">{{ title }}</p>
        <div class="overflow-hidden rounded-xl border border-white/10 bg-black/40 shadow-lg aspect-video">
            <iframe
                v-if="embed.kind === 'youtube' || embed.kind === 'vimeo'"
                :src="embed.src"
                class="size-full"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                title="Video"
            />
            <video v-else class="size-full object-cover" controls playsinline :src="embed.src" />
        </div>
    </div>
</template>
