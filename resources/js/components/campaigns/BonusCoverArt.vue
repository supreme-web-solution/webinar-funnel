<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        subtitle?: string;
        bonusType?: string;
        gradient?: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        subtitle: '',
        bonusType: 'ebook',
        gradient: '',
        size: 'md',
    },
);

const palettes: Record<string, string[][]> = {
    ebook: [
        ['#dc2626', '#7f1d1d'],
        ['#1e3a5f', '#0d9488'],
        ['#b45309', '#78350f'],
        ['#be123c', '#881337'],
    ],
    mini_course: [
        ['#6366f1', '#8b5cf6'],
        ['#7c3aed', '#db2777'],
        ['#4f46e5', '#0ea5e9'],
        ['#0891b2', '#6366f1'],
    ],
};

const resolvedGradient = computed(() => {
    if (props.gradient) return props.gradient;
    const type = props.bonusType === 'mini_course' ? 'mini_course' : 'ebook';
    const set = palettes[type] ?? palettes.ebook;
    const idx = props.title.split('').reduce((sum, ch) => sum + ch.charCodeAt(0), 0) % set.length;
    const [from, to] = set[idx] ?? set[0];
    return `linear-gradient(145deg, ${from} 0%, ${to} 100%)`;
});

const typeBadge = computed(() => {
    if (props.bonusType === 'mini_course') return 'Mini Course';
    if (props.bonusType === 'mini_app') return 'Mini App';
    return 'Ebook';
});

const sizeClass = computed(() => {
    if (props.size === 'sm') return 'bonus-cover--sm';
    if (props.size === 'lg') return 'bonus-cover--lg';
    return 'bonus-cover--md';
});
</script>

<template>
    <div class="bonus-cover" :class="sizeClass" aria-hidden="true">
        <div class="bonus-cover__spine" />
        <div class="bonus-cover__face" :style="{ background: resolvedGradient }">
            <div class="bonus-cover__pattern" />
            <div class="bonus-cover__shine" />
            <span class="bonus-cover__badge">{{ typeBadge }}</span>
            <p class="bonus-cover__title">{{ title }}</p>
            <p v-if="subtitle" class="bonus-cover__subtitle">{{ subtitle }}</p>
        </div>
    </div>
</template>

<style scoped>
.bonus-cover {
    position: relative;
    display: flex;
    flex-shrink: 0;
}

.bonus-cover__spine {
    width: 6px;
    border-radius: 3px 0 0 3px;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.15));
    box-shadow: inset -2px 0 4px rgba(0, 0, 0, 0.2);
}

.bonus-cover__face {
    position: relative;
    overflow: hidden;
    border-radius: 0 10px 10px 0;
    box-shadow:
        0 10px 24px rgba(15, 23, 42, 0.18),
        0 2px 6px rgba(15, 23, 42, 0.08);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 12px 10px;
}

.bonus-cover--sm .bonus-cover__face {
    width: 72px;
    height: 96px;
    padding: 8px 6px;
}

.bonus-cover--md .bonus-cover__face {
    width: 96px;
    height: 128px;
}

.bonus-cover--lg .bonus-cover__face {
    width: 120px;
    height: 160px;
    padding: 14px 12px;
}

.bonus-cover__pattern {
    position: absolute;
    inset: 0;
    opacity: 0.12;
    background: repeating-linear-gradient(
        -45deg,
        transparent,
        transparent 6px,
        rgba(255, 255, 255, 0.15) 6px,
        rgba(255, 255, 255, 0.15) 12px
    );
}

.bonus-cover__shine {
    position: absolute;
    top: -20%;
    right: -30%;
    width: 70%;
    height: 80%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.22) 0%, transparent 70%);
    pointer-events: none;
}

.bonus-cover__badge {
    position: relative;
    z-index: 1;
    align-self: flex-start;
    font-size: 7px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.92);
    background: rgba(0, 0, 0, 0.22);
    padding: 3px 6px;
    border-radius: 999px;
    margin-bottom: auto;
}

.bonus-cover--lg .bonus-cover__badge {
    font-size: 8px;
    padding: 4px 8px;
}

.bonus-cover__title {
    position: relative;
    z-index: 1;
    margin: 0;
    font-size: 9px;
    font-weight: 700;
    line-height: 1.25;
    color: #fff;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.bonus-cover--md .bonus-cover__title {
    font-size: 10px;
}

.bonus-cover--lg .bonus-cover__title {
    font-size: 11px;
    -webkit-line-clamp: 5;
}

.bonus-cover__subtitle {
    position: relative;
    z-index: 1;
    margin: 4px 0 0;
    font-size: 7px;
    line-height: 1.3;
    color: rgba(255, 255, 255, 0.85);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
