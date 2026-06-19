<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CalendarEvent = {
    id: number;
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
    email_subject: string | null;
    last_error: string | null;
};

const props = defineProps<{
    funnel: { id: number; name: string; status: string };
    events: CalendarEvent[];
    currentMonth: number;
    currentYear: number;
    routes: { posts: string; move: string };
}>();

// ─── Navigation ─────────────────────────────────────────────────────────────
function navMonth(delta: number): void {
    let m = props.currentMonth + delta;
    let y = props.currentYear;
    if (m > 12) { m = 1; y++; }
    if (m < 1) { m = 12; y--; }
    router.get(`/funnels/${props.funnel.id}/promotion/calendar`, { month: m, year: y }, { preserveScroll: false });
}

function goToday(): void {
    const now = new Date();
    router.get(`/funnels/${props.funnel.id}/promotion/calendar`, { month: now.getMonth() + 1, year: now.getFullYear() }, { preserveScroll: false });
}

// ─── Calendar grid ──────────────────────────────────────────────────────────
type CalendarDay = { date: Date; isCurrentMonth: boolean };

const calendarWeeks = computed<CalendarDay[][]>(() => {
    const firstDay = new Date(props.currentYear, props.currentMonth - 1, 1);
    const lastDay  = new Date(props.currentYear, props.currentMonth, 0);

    const startDow = (firstDay.getDay() + 6) % 7; // 0 = Mon

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
    const k = dayKey(date);
    return props.events.filter((e) => e.scheduled_for?.slice(0, 10) === k);
}

function unscheduledEvents(): CalendarEvent[] {
    return props.events.filter((e) => !e.scheduled_for);
}

function isToday(date: Date): boolean {
    const t = new Date();
    return date.getDate() === t.getDate() && date.getMonth() === t.getMonth() && date.getFullYear() === t.getFullYear();
}

// ─── Event detail modal ───────────────────────────────────────────────────────
const selectedEvent = ref<CalendarEvent | null>(null);
const rescheduleDate = ref('');
const rescheduling = ref(false);

function openEvent(event: CalendarEvent): void {
    selectedEvent.value = event;
    rescheduleDate.value = event.scheduled_for
        ? new Date(event.scheduled_for).toISOString().slice(0, 16)
        : '';
}

function closeModal(): void {
    selectedEvent.value = null;
    rescheduleDate.value = '';
}

function moveEvent(): void {
    if (!selectedEvent.value || !rescheduleDate.value) return;
    rescheduling.value = true;
    const url = props.routes.move.replace('__POST__', String(selectedEvent.value.id));
    router.patch(url, {
        scheduled_for: new Date(rescheduleDate.value).toISOString(),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Post rescheduled.');
            closeModal();
        },
        onFinish: () => { rescheduling.value = false; },
    });
}

function publishNow(event: CalendarEvent): void {
    router.post(`/funnels/${props.funnel.id}/promotion/posts/${event.id}/publish`, { sync: false }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Queued for publishing.');
            closeModal();
        },
    });
}

// ─── Drag / drop ─────────────────────────────────────────────────────────────
const draggingId = ref<number | null>(null);

function onDragStart(eventId: number, domEvent: DragEvent): void {
    draggingId.value = eventId;
    if (domEvent.dataTransfer) domEvent.dataTransfer.effectAllowed = 'move';
}

function onDropToDay(date: Date): void {
    if (!draggingId.value) return;
    const event = props.events.find((e) => e.id === draggingId.value);
    draggingId.value = null;
    if (!event) return;

    const sourceTime = event.scheduled_for ? new Date(event.scheduled_for) : new Date();
    const target = new Date(date);
    target.setHours(sourceTime.getHours(), sourceTime.getMinutes(), 0, 0);

    const url = props.routes.move.replace('__POST__', String(event.id));
    router.patch(url, {
        scheduled_for: target.toISOString(),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
    }, { preserveScroll: true, onSuccess: () => toast.success('Post moved.') });
}

import { promotionPlatformIcon, promotionPlatformLabel } from '@/lib/promotionPlatforms';

function eventChipClass(type: string): string {
    return {
        image: 'bg-primary/15 text-primary border-primary/20',
        video: 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/20',
        email: 'bg-purple-500/15 text-purple-700 dark:text-purple-400 border-purple-500/20',
        text:  'bg-blue-500/15 text-blue-700 dark:text-blue-400 border-blue-500/20',
    }[type] ?? 'bg-muted text-muted-foreground border-border';
}

function eventTypeIcon(type: string): string {
    return { video: 'heroicons:video-camera', email: 'heroicons:envelope', image: 'heroicons:photo', text: 'heroicons:document-text' }[type] ?? 'heroicons:document-text';
}

const STATUS_META: Record<string, { label: string; dot: string; text: string }> = {
    published:  { label: 'Published',   dot: 'bg-emerald-500',              text: 'text-emerald-600 dark:text-emerald-400' },
    scheduled:  { label: 'Scheduled',   dot: 'bg-blue-500',                 text: 'text-blue-600 dark:text-blue-400' },
    failed:     { label: 'Failed',      dot: 'bg-rose-500',                 text: 'text-rose-600 dark:text-rose-400' },
    generating: { label: 'Generating…', dot: 'bg-amber-500 animate-pulse',  text: 'text-amber-600' },
    ready:      { label: 'Ready',       dot: 'bg-primary',                  text: 'text-primary' },
    draft:      { label: 'Draft',       dot: 'bg-muted-foreground',         text: 'text-muted-foreground' },
};
function statusMeta(s: string) { return STATUS_META[s] ?? STATUS_META['draft']; }

function fmtTime(v: string | null): string {
    if (!v) return '';
    return new Date(v).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function fmtFull(v: string | null): string {
    if (!v) return '';
    return new Date(v).toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const monthLabel = computed(() =>
    new Date(props.currentYear, props.currentMonth - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
);

const isCurrentMonth = computed(() => {
    const t = new Date();
    return props.currentMonth === t.getMonth() + 1 && props.currentYear === t.getFullYear();
});
</script>

<template>
    <Head :title="`Calendar – ${funnel.name}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 md:px-6">

        <!-- ── Header ─────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Link :href="`/funnels/${funnel.id}/edit`" class="hover:text-foreground transition-colors">Funnels</Link>
                    <Icon icon="heroicons:chevron-right" class="size-3" />
                    <Link :href="`/funnels/${funnel.id}/edit`" class="hover:text-foreground transition-colors truncate max-w-[160px]">{{ funnel.name }}</Link>
                    <Icon icon="heroicons:chevron-right" class="size-3" />
                    <span class="text-foreground font-medium">Promotion Calendar</span>
                </div>
                <h1 class="text-xl font-bold tracking-tight">Promotion Calendar</h1>
                <p class="text-sm text-muted-foreground">View, drag-and-drop, and reschedule all promotion posts.</p>
            </div>
            <Button as-child size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90">
                <Link :href="routes.posts">
                    <Icon icon="heroicons:queue-list" class="size-3.5" />
                    Manage Posts
                </Link>
            </Button>
        </div>

        <!-- ── Month navigation ────────────────────────────────────────── -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <Button size="sm" variant="outline" class="h-8 w-8 p-0" @click="navMonth(-1)">
                    <Icon icon="heroicons:chevron-left" class="size-4" />
                </Button>
                <h2 class="text-base font-semibold min-w-[160px] text-center">{{ monthLabel }}</h2>
                <Button size="sm" variant="outline" class="h-8 w-8 p-0" @click="navMonth(1)">
                    <Icon icon="heroicons:chevron-right" class="size-4" />
                </Button>
                <Button v-if="!isCurrentMonth" size="sm" variant="outline" class="h-8 text-xs ml-1" @click="goToday">Today</Button>
            </div>

            <!-- Legend -->
            <div class="hidden sm:flex items-center gap-3 text-[0.65rem] text-muted-foreground">
                <span v-for="[type, cls] in [['Post', 'bg-primary/20'], ['Video', 'bg-amber-500/20'], ['Email', 'bg-purple-500/20']]" :key="type" class="flex items-center gap-1">
                    <span class="size-2.5 rounded" :class="cls" />{{ type }}
                </span>
                <span class="flex items-center gap-1.5">
                    <Icon icon="heroicons:cursor-arrow-rays" class="size-3" />
                    <span>Click to open · drag to move</span>
                </span>
            </div>
        </div>

        <!-- ── Calendar grid ───────────────────────────────────────────── -->
        <div class="rounded-xl border overflow-hidden shadow-sm">
            <!-- Day-of-week headers -->
            <div class="grid grid-cols-7 border-b bg-muted/30">
                <div
                    v-for="d in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']"
                    :key="d"
                    class="py-2.5 text-center text-xs font-semibold text-muted-foreground"
                >{{ d }}</div>
            </div>

            <!-- Weeks -->
            <div v-for="(week, wi) in calendarWeeks" :key="wi" class="grid grid-cols-7">
                <div
                    v-for="(day, di) in week"
                    :key="di"
                    class="min-h-[96px] border-r border-b p-1.5 relative transition-colors"
                    :class="[
                        !day.isCurrentMonth ? 'bg-muted/20 opacity-60' : 'bg-card',
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

                    <!-- Event chips -->
                    <div
                        v-for="event in eventsForDay(day.date).slice(0, 3)"
                        :key="event.id"
                        class="mb-0.5 flex items-center gap-1 rounded border px-1.5 py-0.5 text-[0.58rem] font-medium leading-tight cursor-pointer truncate transition-all hover:opacity-80 hover:shadow-sm select-none"
                        :class="eventChipClass(event.content_type)"
                        draggable="true"
                        @dragstart="onDragStart(event.id, $event)"
                        @click.stop="openEvent(event)"
                    >
                        <Icon :icon="eventTypeIcon(event.content_type)" class="size-2.5 shrink-0" />
                        <span class="truncate">{{ event.title || event.topic || `#${event.id}` }}</span>
                        <span v-if="event.scheduled_for" class="ml-auto shrink-0 opacity-70">{{ fmtTime(event.scheduled_for) }}</span>
                    </div>

                    <div v-if="eventsForDay(day.date).length > 3" class="text-[0.55rem] text-muted-foreground px-1 mt-0.5 cursor-pointer hover:text-foreground" @click="openEvent(eventsForDay(day.date)[3])">
                        +{{ eventsForDay(day.date).length - 3 }} more
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Unscheduled posts ────────────────────────────────────────── -->
        <div v-if="unscheduledEvents().length > 0" class="rounded-xl border border-dashed p-4 space-y-2">
            <p class="text-xs font-semibold text-muted-foreground">Unscheduled posts — click to schedule</p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="event in unscheduledEvents()"
                    :key="event.id"
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs transition-all hover:bg-muted border-border"
                    @click="openEvent(event)"
                >
                    <Icon :icon="eventTypeIcon(event.content_type)" class="size-3" />
                    {{ event.title || event.topic || `Post #${event.id}` }}
                </button>
            </div>
        </div>

        <!-- ── Empty state ─────────────────────────────────────────────── -->
        <div v-if="events.length === 0" class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-12 text-center">
            <div class="size-12 rounded-full bg-muted flex items-center justify-center">
                <Icon icon="heroicons:calendar-days" class="size-6 text-muted-foreground" />
            </div>
            <div>
                <p class="text-sm font-semibold">No posts scheduled this month</p>
                <p class="text-xs text-muted-foreground mt-0.5">Head to the posts list to create and schedule content.</p>
            </div>
            <Button as-child size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground">
                <Link :href="routes.posts">
                    <Icon icon="heroicons:plus" class="size-3.5" />
                    Create posts
                </Link>
            </Button>
        </div>
    </div>

    <!-- ── Event detail modal ──────────────────────────────────────────────── -->
    <Dialog :open="selectedEvent !== null" @update:open="(v) => { if (!v) closeModal(); }">
        <DialogContent class="max-w-lg p-0 overflow-hidden flex flex-col gap-0 max-h-[90vh]">
            <template v-if="selectedEvent">
                <!-- Colour bar + header -->
                <div class="shrink-0">
                    <div class="h-1 w-full" :class="{
                        'bg-primary': selectedEvent.content_type === 'image',
                        'bg-amber-500': selectedEvent.content_type === 'video',
                        'bg-purple-500': selectedEvent.content_type === 'email',
                        'bg-blue-500': selectedEvent.content_type === 'text',
                    }" />
                    <div class="px-5 pt-4 pb-3 border-b">
                        <DialogHeader>
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[0.65rem] font-semibold border capitalize" :class="eventChipClass(selectedEvent.content_type)">
                                    <Icon :icon="eventTypeIcon(selectedEvent.content_type)" class="size-3" />
                                    {{ selectedEvent.content_type }}
                                </span>
                                <span class="flex items-center gap-1 text-xs" :class="statusMeta(selectedEvent.status).text">
                                    <span class="size-1.5 rounded-full inline-block" :class="statusMeta(selectedEvent.status).dot" />
                                    {{ statusMeta(selectedEvent.status).label }}
                                </span>
                            </div>
                            <DialogTitle class="text-base leading-snug">
                                {{ selectedEvent.title || selectedEvent.topic || `Post #${selectedEvent.id}` }}
                            </DialogTitle>
                            <DialogDescription class="sr-only">Post details and reschedule</DialogDescription>
                        </DialogHeader>
                    </div>
                </div>

                <!-- Scrollable body -->
                <div class="flex-1 min-h-0 overflow-y-auto px-5 py-4 space-y-4">

                    <!-- Error -->
                    <div v-if="selectedEvent.last_error" class="flex items-start gap-2 rounded-lg border border-rose-500/20 bg-rose-500/5 px-3 py-2 text-xs text-rose-600">
                        <Icon icon="heroicons:exclamation-triangle" class="size-3.5 shrink-0 mt-0.5" />
                        {{ selectedEvent.last_error }}
                    </div>

                    <!-- Content preview -->
                    <div v-if="selectedEvent.email_subject || selectedEvent.text_body" class="space-y-2">
                        <p v-if="selectedEvent.email_subject" class="text-xs font-semibold text-muted-foreground">
                            Subject: <span class="text-foreground font-normal">{{ selectedEvent.email_subject }}</span>
                        </p>
                        <p v-if="selectedEvent.text_body" class="text-sm text-muted-foreground leading-relaxed line-clamp-4">
                            {{ selectedEvent.text_body }}
                        </p>
                    </div>

                    <!-- Dates row -->
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <p class="text-[0.6rem] font-semibold text-muted-foreground uppercase tracking-wide mb-1">Scheduled for</p>
                            <p class="font-medium">{{ selectedEvent.scheduled_for ? fmtFull(selectedEvent.scheduled_for) : '— not scheduled yet' }}</p>
                        </div>
                        <div v-if="selectedEvent.published_at">
                            <p class="text-[0.6rem] font-semibold text-muted-foreground uppercase tracking-wide mb-1">Published at</p>
                            <p class="text-emerald-600 font-medium">{{ fmtFull(selectedEvent.published_at) }}</p>
                        </div>
                    </div>

                    <!-- Audience / platforms -->
                    <div v-if="selectedEvent.content_type === 'email'" class="flex items-start gap-2 rounded-lg border border-purple-500/20 bg-purple-500/5 px-3 py-2">
                        <Icon icon="heroicons:users" class="size-3.5 text-purple-500 shrink-0 mt-0.5" />
                        <p class="text-xs text-purple-700 dark:text-purple-400 font-medium">Sends to funnel leads list</p>
                    </div>
                    <div v-else-if="selectedEvent.platforms?.length" class="flex items-center gap-2">
                        <p class="text-[0.65rem] text-muted-foreground font-semibold uppercase tracking-wide">Platforms</p>
                        <div class="flex items-center gap-1.5">
                            <span v-for="p in selectedEvent.platforms" :key="p" class="flex items-center gap-1 text-xs text-muted-foreground">
                                <Icon :icon="promotionPlatformIcon(p)" class="size-3.5" />
                                {{ promotionPlatformLabel(p) }}
                            </span>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div v-if="selectedEvent.cta_url" class="flex items-center gap-2 text-xs">
                        <p class="text-[0.65rem] text-muted-foreground font-semibold uppercase tracking-wide shrink-0">CTA</p>
                        <a :href="selectedEvent.cta_url" target="_blank" class="text-primary hover:underline truncate">
                            {{ selectedEvent.cta_label || selectedEvent.cta_url }}
                        </a>
                    </div>

                    <!-- ── Reschedule ──────────────────────────────────────── -->
                    <div class="space-y-2 pt-1 border-t">
                        <Label class="text-xs font-semibold">Schedule / reschedule</Label>
                        <Input v-model="rescheduleDate" type="datetime-local" class="h-9 text-sm" />
                        <p class="text-[0.65rem] text-muted-foreground">Pick a new date and time, then click Save.</p>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="shrink-0 border-t px-5 py-3.5 flex items-center gap-2 bg-muted/10">
                    <Button
                        size="sm"
                        class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90 flex-1"
                        :disabled="!rescheduleDate || rescheduling"
                        @click="moveEvent"
                    >
                        <Icon :icon="rescheduling ? 'heroicons:arrow-path' : 'heroicons:calendar-days'" class="size-3.5" :class="rescheduling ? 'animate-spin' : ''" />
                        {{ rescheduling ? 'Saving…' : 'Save date' }}
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-8 text-xs gap-1.5 flex-1"
                        :disabled="selectedEvent.status === 'published' || selectedEvent.status === 'publishing'"
                        @click="publishNow(selectedEvent)"
                    >
                        <Icon icon="heroicons:paper-airplane" class="size-3.5" />
                        Publish now
                    </Button>
                    <Button
                        as-child
                        size="sm"
                        variant="ghost"
                        class="h-8 text-xs px-2.5 text-muted-foreground"
                        title="Open in posts list"
                    >
                        <Link :href="`/funnels/${funnel.id}/promotion/posts`">
                            <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                        </Link>
                    </Button>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
