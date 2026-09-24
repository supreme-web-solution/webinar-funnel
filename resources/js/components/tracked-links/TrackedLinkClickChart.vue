<script setup lang="ts">
import { computed } from 'vue';

export type ClickChartDay = {
    label: string;
    date: string;
    clicks: number;
};

const props = defineProps<{
    days: ClickChartDay[];
}>();

const maxValue = computed(() => {
    let max = 1;
    for (const d of props.days) {
        max = Math.max(max, d.clicks);
    }
    return max;
});

function barHeight(value: number): number {
    if (value <= 0) return 0;
    return Math.max(4, Math.round((value / maxValue.value) * 100));
}

const total = computed(() => props.days.reduce((s, d) => s + d.clicks, 0));
const hasData = computed(() => props.days.some((d) => d.clicks > 0));
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-medium text-muted-foreground">Clicks (7 days)</p>
            <span class="text-xs tabular-nums text-muted-foreground">{{ total }} total</span>
        </div>

        <div v-if="!hasData" class="flex h-[100px] flex-col items-center justify-center rounded-lg border border-dashed border-border/70 bg-muted/20 text-center">
            <p class="text-xs text-muted-foreground">No clicks this week yet</p>
        </div>

        <div v-else class="flex items-end gap-1 sm:gap-2">
            <div
                v-for="day in days"
                :key="day.date"
                class="group flex min-w-0 flex-1 flex-col items-center gap-1"
            >
                <span class="text-[0.6rem] tabular-nums text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100">
                    {{ day.clicks }}
                </span>
                <div class="flex h-[80px] w-full items-end justify-center">
                    <div
                        class="w-[55%] max-w-6 rounded-t-md fill-brand-gradient transition-all "
                        :style="{ height: `${barHeight(day.clicks)}%` }"
                        :title="`${day.clicks} clicks on ${day.date}`"
                    />
                </div>
                <span class="text-[0.6rem] font-medium uppercase tracking-wide text-muted-foreground">
                    {{ day.label }}
                </span>
            </div>
        </div>
    </div>
</template>
