<script setup lang="ts">
import { computed } from 'vue';

export type ChartDay = {
    label: string;
    date: string;
    leads: number;
    views: number;
};

const props = defineProps<{
    days: ChartDay[];
}>();

const maxValue = computed(() => {
    let max = 1;
    for (const d of props.days) {
        max = Math.max(max, d.leads, d.views);
    }
    return max;
});

function barHeight(value: number): number {
    if (value <= 0) return 0;
    return Math.max(4, Math.round((value / maxValue.value) * 100));
}

const hasData = computed(() => props.days.some((d) => d.leads > 0 || d.views > 0));
</script>

<template>
    <div class="flex h-full min-h-[140px] flex-col">
        <div class="mb-2 flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 text-[0.65rem]">
                <span class="flex items-center gap-1.5">
                    <span class="size-2 rounded-sm bg-teal-500" />
                    Leads
                </span>
                <span class="flex items-center gap-1.5 text-muted-foreground">
                    <span class="size-2 rounded-sm bg-cyan-300" />
                    Views
                </span>
            </div>
        </div>

        <div v-if="!hasData" class="flex flex-1 flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-border/70 bg-muted/20 text-center">
            <p class="text-xs font-medium text-muted-foreground">No activity yet this week</p>
            <p class="text-[0.65rem] text-muted-foreground/80">Publish a campaign to see leads & views here</p>
        </div>

        <div v-else class="flex flex-1 items-end gap-1 sm:gap-2">
            <div
                v-for="day in days"
                :key="day.date"
                class="group flex min-w-0 flex-1 flex-col items-center gap-1"
            >
                <div class="flex h-[100px] w-full items-end justify-center gap-0.5 sm:gap-1">
                    <div
                        class="w-[38%] max-w-5 rounded-t-md bg-linear-to-t from-teal-600 to-teal-400 transition-all group-hover:from-teal-700 group-hover:to-teal-500"
                        :style="{ height: `${barHeight(day.leads)}%` }"
                        :title="`${day.leads} leads`"
                    />
                    <div
                        class="w-[38%] max-w-5 rounded-t-md bg-linear-to-t from-cyan-400/80 to-cyan-200/90 transition-all group-hover:from-cyan-500/80 group-hover:to-cyan-300"
                        :style="{ height: `${barHeight(day.views)}%` }"
                        :title="`${day.views} views`"
                    />
                </div>
                <span class="text-[0.6rem] font-medium uppercase tracking-wide text-muted-foreground sm:text-[0.65rem]">
                    {{ day.label }}
                </span>
            </div>
        </div>
    </div>
</template>
