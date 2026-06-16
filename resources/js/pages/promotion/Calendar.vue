<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CalendarEvent = {
    id: number;
    funnel_id: number;
    funnel_name: string;
    title: string | null;
    topic: string | null;
    content_type: string;
    platforms: string[];
    status: string;
    scheduled_for: string | null;
    published_at: string | null;
    cta_url: string | null;
    cta_label: string | null;
    text_body: string | null;
};

const props = defineProps<{
    events: CalendarEvent[];
    currentMonth: number;
    currentYear: number;
    routes: { move: string };
}>();

// ─── Month navigation ────────────────────────────────────────────────────────
function navMonth(delta: number): void {
    let m = props.currentMonth + delta;
    let y = props.currentYear;
    if (m > 12) { m = 1; y++; }
    if (m < 1)  { m = 12; y--; }
    router.get('/promotion/calendar', { month: m, year: y }, { preserveScroll: false });
}

function goToday(): void {
    const t = new Date();
    router.get('/promotion/calendar', { month: t.getMonth() + 1, year: t.getFullYear() }, { preserveScroll: false });
}

const isCurrentMonth = computed(() => {
    const t = new Date();
    return props.currentMonth === t.getMonth() + 1 && props.currentYear === t.getFullYear();
});

const monthLabel = computed(() =>
    new Date(props.currentYear, props.currentMonth - 1, 1)
        .toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
);

// ─── Calendar grid ───────────────────────────────────────────────────────────
type CalendarDay = { date: Date; isCurrentMonth: boolean };

const calendarWeeks = computed<CalendarDay[][]>(() => {
    const firstDay = new Date(props.currentYear, props.currentMonth - 1, 1);
    const lastDay  = new Date(props.currentYear, props.currentMonth, 0);
    const startDow = (firstDay.getDay() + 6) % 7; // Mon = 0

    const cells: CalendarDay[] = [];

    for (let i = startDow - 1; i >= 0; i--) {
        const d = new Date(firstDay);
        d.setDate(d.getDate() - i - 1);
        cells.push({ date: d, isCurrentMonth: false });
    }
    for (let d = 1; d <= lastDay.getDate(); d++) {
        cells.push({ date: new Date(props.currentYear, props.currentMonth - 1, d), isCurrentMonth: true });
    }
    while (cells.length % 7 !== 0) {
        const d = new Date(lastDay);
        d.setDate(lastDay.getDate() + (cells.length - startDow - lastDay.getDate() + 1));
        cells.push({ date: d, isCurrentMonth: false });
    }

    const weeks: CalendarDay[][] = [];
    for (let i = 0; i < cells.length; i += 7) {
        weeks.push(cells.slice(i, i + 7));
    }
    return weeks;
});

function dayKey(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function eventsForDay(date: Date): CalendarEvent[] {
    return props.events.filter((e) => e.scheduled_for?.slice(0, 10) === dayKey(date));
}

function unscheduled(): CalendarEvent[] {
    return props.events.filter((e) => !e.scheduled_for);
}

function isToday(date: Date): boolean {
    const t = new Date();
    return date.getDate() === t.getDate() && date.getMonth() === t.getMonth() && date.getFullYear() === t.getFullYear();
}

// ─── Quick search / filter ───────────────────────────────────────────────────
const search = ref('');
const filterFunnel = ref('');

const funnelOptions = computed(() => {
    const seen = new Map<number, string>();
    props.events.forEach((e) => {
        if (!seen.has(e.funnel_id)) seen.set(e.funnel_id, e.funnel_name);
    });
    return [...seen.entries()].map(([id, name]) => ({ id, name }));
});

const filteredEventIds = computed<Set<number>>(() => {
    const q = search.value.trim().toLowerCase();
    const fid = filterFunnel.value ? Number(filterFunnel.value) : null;
    return new Set(
        props.events
            .filter((e) => {
                if (fid && e.funnel_id !== fid) return false;
                if (!q) return true;
                return (
                    (e.title  ?? '').toLowerCase().includes(q) ||
                    (e.topic  ?? '').toLowerCase().includes(q) ||
                    e.funnel_name.toLowerCase().includes(q) ||
                    e.content_type.toLowerCase().includes(q)
                );
            })
            .map((e) => e.id),
    );
});

function visibleForDay(date: Date): CalendarEvent[] {
    return eventsForDay(date).filter((e) => filteredEventIds.value.has(e.id));
}

function visibleUnscheduled(): CalendarEvent[] {
    return unscheduled().filter((e) => filteredEventIds.value.has(e.id));
}

// ─── Selected event ──────────────────────────────────────────────────────────
const selectedEvent = ref<CalendarEvent | null>(null);
const rescheduleDate = ref('');

function selectEvent(event: CalendarEvent): void {
    selectedEvent.value = selectedEvent.value?.id === event.id ? null : event;
    rescheduleDate.value = event.scheduled_for
        ? new Date(event.scheduled_for).toISOString().slice(0, 16)
        : '';
}

function moveEvent(): void {
    if (!selectedEvent.value || !rescheduleDate.value) return;
    const url = props.routes.move.replace('__POST__', String(selectedEvent.value.id));
    router.patch(url, {
        scheduled_for: new Date(rescheduleDate.value).toISOString(),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
    }, {
        preserveScroll: true,
        onSuccess: () => { selectedEvent.value = null; toast.success('Post rescheduled.'); },
    });
}

function publishNow(event: CalendarEvent): void {
    router.post(`/funnels/${event.funnel_id}/promotion/posts/${event.id}/publish`, { sync: false }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Queued for publishing.'),
    });
}

// ─── Drag / drop ─────────────────────────────────────────────────────────────
const draggingId = ref<number | null>(null);

function onDragStart(eventId: number, e: DragEvent): void {
    draggingId.value = eventId;
    if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
}

function onDropToDay(date: Date): void {
    if (!draggingId.value) return;
    const event = props.events.find((ev) => ev.id === draggingId.value);
    draggingId.value = null;
    if (!event) return;

    const source = event.scheduled_for ? new Date(event.scheduled_for) : new Date();
    const target = new Date(date);
    target.setHours(source.getHours(), source.getMinutes(), 0, 0);

    const url = props.routes.move.replace('__POST__', String(event.id));
    router.patch(url, {
        scheduled_for: target.toISOString(),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
    }, { preserveScroll: true, onSuccess: () => toast.success('Post moved.') });
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
const PLATFORM_META: Record<string, { label: string; icon: string }> = {
    twitter: { label: 'X (Twitter)', icon: 'simple-icons:x'      },
    youtube: { label: 'YouTube',     icon: 'simple-icons:youtube' },
    reddit:  { label: 'Reddit',      icon: 'simple-icons:reddit'  },
};

function chipClass(type: string): string {
    return {
        image: 'bg-primary/15 text-primary border-primary/20',
        video: 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/20',
        email: 'bg-purple-500/15 text-purple-700 dark:text-purple-400 border-purple-500/20',
        text:  'bg-blue-500/15 text-blue-700 dark:text-blue-400 border-blue-500/20',
    }[type] ?? 'bg-muted text-muted-foreground border-border';
}

function typeIcon(type: string): string {
    return { video: 'heroicons:video-camera', email: 'heroicons:envelope', image: 'heroicons:photo', text: 'heroicons:document-text' }[type] ?? 'heroicons:document-text';
}

function statusMeta(s: string) {
    const m: Record<string, { label: string; dot: string; text: string }> = {
        published:  { label: 'Published',   dot: 'bg-emerald-500',             text: 'text-emerald-600 dark:text-emerald-400' },
        scheduled:  { label: 'Scheduled',   dot: 'bg-blue-500',                text: 'text-blue-600 dark:text-blue-400'       },
        failed:     { label: 'Failed',      dot: 'bg-rose-500',                text: 'text-rose-600 dark:text-rose-400'       },
        generating: { label: 'Generating…', dot: 'bg-amber-500 animate-pulse', text: 'text-amber-600'                         },
        ready:      { label: 'Ready',       dot: 'bg-primary',                 text: 'text-primary'                           },
        draft:      { label: 'Draft',       dot: 'bg-muted-foreground',        text: 'text-muted-foreground'                  },
    };
    return m[s] ?? m['draft'];
}

function fmtFull(v: string | null): string {
    if (!v) return '';
    return new Date(v).toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const totalVisible = computed(() => filteredEventIds.value.size);
</script>

<template>
    <Head title="Promotion Calendar" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 px-4 py-6 md:px-6">

        <!-- ── Header ─────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight flex items-center gap-2">
                    <Icon icon="heroicons:calendar-days" class="size-5 text-primary" />
                    Promotion Calendar
                </h1>
                <p class="text-sm text-muted-foreground mt-0.5">
                    All scheduled posts across every funnel — drag to reschedule.
                </p>
            </div>
            <Link href="/funnels" class="text-xs text-muted-foreground hover:text-foreground flex items-center gap-1 transition-colors">
                <Icon icon="heroicons:funnel" class="size-3.5" />
                My Funnels
            </Link>
        </div>

        <!-- ── Toolbar: month nav + filters ──────────────────────────── -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Month navigation -->
            <div class="flex items-center gap-1">
                <Button size="sm" variant="outline" class="h-8 w-8 p-0" @click="navMonth(-1)">
                    <Icon icon="heroicons:chevron-left" class="size-4" />
                </Button>
                <span class="text-sm font-semibold min-w-[150px] text-center">{{ monthLabel }}</span>
                <Button size="sm" variant="outline" class="h-8 w-8 p-0" @click="navMonth(1)">
                    <Icon icon="heroicons:chevron-right" class="size-4" />
                </Button>
                <Button v-if="!isCurrentMonth" size="sm" variant="outline" class="h-8 text-xs ml-1" @click="goToday">Today</Button>
            </div>

            <!-- Funnel filter -->
            <select
                v-if="funnelOptions.length > 1"
                v-model="filterFunnel"
                class="h-8 rounded-lg border bg-background px-3 text-xs text-muted-foreground"
            >
                <option value="">All funnels ({{ funnelOptions.length }})</option>
                <option v-for="f in funnelOptions" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>

            <!-- Search -->
            <div class="relative ml-auto">
                <Icon icon="heroicons:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                <Input v-model="search" class="h-8 pl-7 text-xs w-48" placeholder="Search topic, funnel…" />
            </div>

            <!-- Count -->
            <span class="text-xs text-muted-foreground">{{ totalVisible }} post{{ totalVisible !== 1 ? 's' : '' }}</span>

            <!-- Legend -->
            <div class="hidden lg:flex items-center gap-3 text-[0.65rem] text-muted-foreground">
                <span v-for="[label, cls] in [['Post', 'bg-primary/20'], ['Video', 'bg-amber-500/20'], ['Email', 'bg-purple-500/20'], ['Text', 'bg-blue-500/20']]"
                      :key="label" class="flex items-center gap-1">
                    <span class="size-2.5 rounded" :class="cls" />{{ label }}
                </span>
            </div>
        </div>

        <!-- ── Calendar grid ───────────────────────────────────────────── -->
        <div class="rounded-xl border overflow-hidden shadow-sm">
            <!-- Day headers -->
            <div class="grid grid-cols-7 border-b bg-muted/30">
                <div v-for="d in ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']" :key="d"
                     class="py-2.5 text-center text-xs font-semibold text-muted-foreground">
                    {{ d }}
                </div>
            </div>

            <!-- Weeks -->
            <div v-for="(week, wi) in calendarWeeks" :key="wi" class="grid grid-cols-7">
                <div
                    v-for="(day, di) in week"
                    :key="di"
                    class="min-h-[110px] border-r border-b p-1.5 relative transition-colors"
                    :class="[
                        !day.isCurrentMonth ? 'bg-muted/20 opacity-50' : 'bg-card',
                        draggingId ? 'hover:bg-primary/5 cursor-copy' : '',
                    ]"
                    @dragover.prevent
                    @drop="onDropToDay(day.date)"
                >
                    <!-- Day number -->
                    <div class="flex items-center justify-start mb-1">
                        <span
                            class="text-[0.65rem] font-semibold flex h-5 w-5 items-center justify-center rounded-full"
                            :class="isToday(day.date)
                                ? 'bg-primary text-primary-foreground'
                                : day.isCurrentMonth ? 'text-foreground' : 'text-muted-foreground/40'"
                        >{{ day.date.getDate() }}</span>
                    </div>

                    <!-- Events (up to 3 visible) -->
                    <div
                        v-for="event in visibleForDay(day.date).slice(0, 3)"
                        :key="event.id"
                        class="mb-0.5 flex items-center gap-1 rounded border px-1.5 py-0.5 text-[0.56rem] font-medium leading-tight cursor-pointer truncate transition-opacity hover:opacity-80"
                        :class="[chipClass(event.content_type), selectedEvent?.id === event.id ? 'ring-1 ring-primary' : '']"
                        draggable="true"
                        @dragstart="onDragStart(event.id, $event)"
                        @click.stop="selectEvent(event)"
                    >
                        <Icon :icon="typeIcon(event.content_type)" class="size-2.5 shrink-0" />
                        <span class="truncate">{{ event.title || event.topic || `#${event.id}` }}</span>
                    </div>

                    <div v-if="visibleForDay(day.date).length > 3"
                         class="text-[0.55rem] text-muted-foreground px-1 mt-0.5 cursor-pointer hover:text-primary"
                         @click="selectEvent(visibleForDay(day.date)[3])">
                        +{{ visibleForDay(day.date).length - 3 }} more
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Unscheduled posts ─────────────────────────────────────── -->
        <div v-if="visibleUnscheduled().length > 0" class="rounded-xl border border-dashed p-4 space-y-2.5">
            <div class="flex items-center gap-2">
                <Icon icon="heroicons:calendar-x-mark" class="size-4 text-muted-foreground" />
                <p class="text-xs font-semibold">Unscheduled posts ({{ visibleUnscheduled().length }})</p>
                <p class="text-xs text-muted-foreground">Click any post to schedule it.</p>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="event in visibleUnscheduled()"
                    :key="event.id"
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs transition-all hover:bg-muted"
                    :class="[chipClass(event.content_type), selectedEvent?.id === event.id ? 'ring-1 ring-primary' : '']"
                    @click="selectEvent(event)"
                >
                    <Icon :icon="typeIcon(event.content_type)" class="size-3 shrink-0" />
                    <span class="max-w-[160px] truncate">{{ event.title || event.topic || `Post #${event.id}` }}</span>
                    <span class="text-[0.6rem] opacity-70 shrink-0">— {{ event.funnel_name }}</span>
                </button>
            </div>
        </div>

        <!-- ── Selected event detail panel ──────────────────────────── -->
        <Card v-if="selectedEvent" class="border-primary/30 shadow-sm">
            <CardContent class="p-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <!-- Event info -->
                    <div class="flex-1 min-w-0">
                        <!-- Header row -->
                        <div class="flex items-start gap-3 mb-3">
                            <div class="size-9 rounded-lg flex items-center justify-center shrink-0" :class="chipClass(selectedEvent.content_type)">
                                <Icon :icon="typeIcon(selectedEvent.content_type)" class="size-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-semibold leading-tight">
                                    {{ selectedEvent.title || selectedEvent.topic || `Post #${selectedEvent.id}` }}
                                </h3>
                                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                    <Link
                                        :href="`/funnels/${selectedEvent.funnel_id}/promotion/posts`"
                                        class="text-[0.65rem] text-primary hover:underline flex items-center gap-0.5"
                                    >
                                        <Icon icon="heroicons:funnel" class="size-2.5" />
                                        {{ selectedEvent.funnel_name }}
                                    </Link>
                                    <span class="text-muted-foreground/40">·</span>
                                    <span class="text-[0.65rem] capitalize text-muted-foreground">{{ selectedEvent.content_type }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="size-1.5 rounded-full" :class="statusMeta(selectedEvent.status).dot" />
                                <span class="text-xs" :class="statusMeta(selectedEvent.status).text">{{ statusMeta(selectedEvent.status).label }}</span>
                            </div>
                        </div>

                        <!-- Text preview -->
                        <p v-if="selectedEvent.text_body" class="text-xs text-muted-foreground leading-relaxed line-clamp-3 mb-3 bg-muted/30 rounded-lg px-3 py-2">
                            {{ selectedEvent.text_body }}
                        </p>

                        <!-- Metadata grid -->
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                            <div v-if="selectedEvent.scheduled_for">
                                <p class="text-[0.6rem] font-medium text-muted-foreground uppercase tracking-wide mb-0.5">Scheduled</p>
                                <p>{{ fmtFull(selectedEvent.scheduled_for) }}</p>
                            </div>
                            <div v-if="selectedEvent.published_at">
                                <p class="text-[0.6rem] font-medium text-muted-foreground uppercase tracking-wide mb-0.5">Published</p>
                                <p class="text-emerald-600">{{ fmtFull(selectedEvent.published_at) }}</p>
                            </div>
                            <div v-if="selectedEvent.platforms?.length">
                                <p class="text-[0.6rem] font-medium text-muted-foreground uppercase tracking-wide mb-0.5">Platforms</p>
                                <div class="flex items-center gap-1.5">
                                    <Icon v-for="p in selectedEvent.platforms" :key="p"
                                          :icon="PLATFORM_META[p]?.icon ?? 'heroicons:share'"
                                          class="size-3.5 text-muted-foreground" :title="PLATFORM_META[p]?.label ?? p" />
                                </div>
                            </div>
                            <div v-if="selectedEvent.cta_url">
                                <p class="text-[0.6rem] font-medium text-muted-foreground uppercase tracking-wide mb-0.5">CTA</p>
                                <a :href="selectedEvent.cta_url" target="_blank" class="text-primary hover:underline truncate block max-w-[160px]">
                                    {{ selectedEvent.cta_label || selectedEvent.cta_url }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Reschedule + actions -->
                    <div class="shrink-0 flex flex-col gap-2 sm:min-w-[220px]">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Reschedule to</Label>
                            <Input v-model="rescheduleDate" type="datetime-local" class="h-9 text-sm" />
                        </div>
                        <Button size="sm" class="h-8 text-xs w-full bg-primary text-primary-foreground hover:opacity-90 gap-1.5" :disabled="!rescheduleDate" @click="moveEvent">
                            <Icon icon="heroicons:calendar-days" class="size-3.5" />
                            Reschedule
                        </Button>
                        <Button size="sm" variant="outline" class="h-8 text-xs w-full gap-1.5" @click="publishNow(selectedEvent)">
                            <Icon icon="heroicons:paper-airplane" class="size-3.5" />
                            Publish now
                        </Button>
                        <Button as-child size="sm" variant="ghost" class="h-8 text-xs w-full gap-1.5 text-muted-foreground">
                            <Link :href="`/funnels/${selectedEvent.funnel_id}/promotion/posts`">
                                <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                Open funnel posts
                            </Link>
                        </Button>
                        <button type="button" class="text-xs text-muted-foreground hover:text-foreground mt-1 text-center" @click="selectedEvent = null">
                            Dismiss
                        </button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- ── Empty state ─────────────────────────────────────────────── -->
        <div v-if="events.length === 0" class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-14 text-center">
            <div class="size-12 rounded-full bg-muted flex items-center justify-center">
                <Icon icon="heroicons:calendar-days" class="size-6 text-muted-foreground" />
            </div>
            <div>
                <p class="text-sm font-semibold">No posts this month</p>
                <p class="text-xs text-muted-foreground mt-0.5">Go to a funnel's promotion posts to create and schedule content.</p>
            </div>
            <Button as-child size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground">
                <Link href="/funnels">
                    <Icon icon="heroicons:funnel" class="size-3.5" />
                    My Funnels
                </Link>
            </Button>
        </div>
    </div>
</template>
