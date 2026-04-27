<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

/* ─── Types ─────────────────────────────────────────────────────────────── */
interface Keyword {
    id: number;
    name: string;
    is_active: boolean;
    email_notifications: boolean;
    platforms: string[];
    mentions_count: number;
    created_at: string;
}

interface Mention {
    id: number;
    keyword_id: number;
    title: string | null;
    content: string | null;
    source: string | null;
    source_type: string;
    username: string | null;
    like_count: number;
    retweet_count: number;
    comments_count: number;
    views: number | null;
    votes: number | null;
    permalink: string | null;
    posted_at: string | null;
    keyword: { id: number; name: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator {
    data: Mention[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Stats {
    total: number;
    this_week: number;
    keywords_count: number;
    platforms: Record<string, number>;
}

/* ─── Props ──────────────────────────────────────────────────────────────── */
const props = defineProps<{
    keywords: Keyword[];
    mentions: Paginator;
    stats: Stats;
    filters: { search?: string; platform?: string; keyword_id?: number | string };
}>();

/* ─── Keyword form ───────────────────────────────────────────────────────── */
const keywordForm = useForm({ name: '', platforms: ['reddit', 'youtube', 'twitter', 'news'] });
const addingKeyword = ref(false);

function submitKeyword() {
    keywordForm.post('/mentions/keywords', {
        preserveScroll: true,
        onSuccess: () => {
            keywordForm.reset();
            addingKeyword.value = false;
        },
    });
}

function toggleActive(keyword: Keyword) {
    router.patch(`/mentions/keywords/${keyword.id}`, {
        is_active: !keyword.is_active,
    }, { preserveScroll: true });
}

function toggleNotifications(keyword: Keyword) {
    router.patch(`/mentions/keywords/${keyword.id}`, {
        email_notifications: !keyword.email_notifications,
    }, { preserveScroll: true });
}

function deleteKeyword(keyword: Keyword) {
    if (!confirm(`Delete keyword "${keyword.name}" and all its mentions?`)) return;
    router.delete(`/mentions/keywords/${keyword.id}`, { preserveScroll: true });
}

function fetchNow(keyword: Keyword) {
    router.post(`/mentions/keywords/${keyword.id}/fetch`, {}, { preserveScroll: true });
}

/* ─── Mention filters ────────────────────────────────────────────────────── */
const search     = ref(props.filters.search ?? '');
const platform   = ref(props.filters.platform ?? '');
const keywordId  = ref<number | string>(props.filters.keyword_id ?? '');

let debounce: ReturnType<typeof setTimeout>;
watch([search, platform, keywordId], ([s, p, k]) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/mentions', {
            search: s || undefined,
            platform: p || undefined,
            keyword_id: k || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
});

/* ─── Platform config ────────────────────────────────────────────────────── */
const PLATFORMS: Record<string, { label: string; icon: string; color: string; bg: string }> = {
    Reddit:  { label: 'Reddit',  icon: 'simple-icons:reddit',  color: '#ff6b35', bg: 'rgba(255,69,0,0.12)' },
    YouTube: { label: 'YouTube', icon: 'simple-icons:youtube', color: '#ff4444', bg: 'rgba(255,0,0,0.12)' },
    Twitter: { label: 'Twitter', icon: 'simple-icons:x',       color: '#e2e8f0', bg: 'rgba(255,255,255,0.08)' },
    News:    { label: 'News',    icon: 'heroicons:newspaper',   color: '#4e9af1', bg: 'rgba(26,115,232,0.12)' },
};

function platformConfig(type: string) {
    return PLATFORMS[type] ?? { label: type, icon: 'heroicons:globe-alt', color: '#94a3b8', bg: 'rgba(148,163,184,0.12)' };
}

/* ─── Platform filter tabs ───────────────────────────────────────────────── */
const platformTabs = computed(() => {
    const all = { key: '', label: 'All', count: props.stats.total };
    const entries = Object.entries(props.stats.platforms ?? {})
        .map(([k, v]) => ({ key: k, label: k, count: v as number }))
        .sort((a, b) => b.count - a.count);
    return [all, ...entries];
});

/* ─── Helpers ────────────────────────────────────────────────────────────── */
function fmtNum(n: number | null | undefined): string {
    if (!n) return '0';
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M';
    if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
    return String(n);
}

function fmtDate(dt: string | null): string {
    if (!dt) return '';
    const d = new Date(dt);
    const now = Date.now();
    const diff = (now - d.getTime()) / 1000;
    if (diff < 60)     return 'just now';
    if (diff < 3600)   return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400)  return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function truncate(text: string | null, len = 200): string {
    if (!text) return '';
    return text.length > len ? text.slice(0, len) + '…' : text;
}

/* ─── Platform toggle (keyword form) ────────────────────────────────────── */
const PLATFORM_OPTIONS = ['reddit', 'youtube', 'twitter', 'news'];
function togglePlatform(p: string) {
    const idx = keywordForm.platforms.indexOf(p);
    if (idx === -1) keywordForm.platforms.push(p);
    else keywordForm.platforms.splice(idx, 1);
}
</script>

<template>
    <Head title="Brand Mentions" />

    <div class="flex flex-col gap-6 p-4 md:p-6 w-full max-w-screen-xl mx-auto">

        <!-- ── Page header ── -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-foreground">Brand Mentions</h1>
                <p class="text-sm text-muted-foreground mt-0.5">
                    Track keywords across Reddit, YouTube, Twitter, and News in real-time.
                </p>
            </div>
            <Button
                size="sm"
                class="gap-1.5 shrink-0 self-start bg-primary text-primary-foreground hover:opacity-90"
                @click="addingKeyword = !addingKeyword"
            >
                <Icon icon="heroicons:plus" class="size-3.5" />
                Add Keyword
            </Button>
        </div>

        <!-- ── Stats ── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <Card class="border shadow-sm">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">Total Mentions</p>
                            <p class="text-2xl font-bold text-foreground mt-1">{{ stats.total.toLocaleString() }}</p>
                        </div>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10">
                            <Icon icon="heroicons:chat-bubble-left-right" class="size-5 text-primary" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border shadow-sm">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">This Week</p>
                            <p class="text-2xl font-bold text-[#40E0D0] mt-1">{{ stats.this_week.toLocaleString() }}</p>
                        </div>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-[#40E0D0]/10">
                            <Icon icon="heroicons:arrow-trending-up" class="size-5 text-[#40E0D0]" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border shadow-sm">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">Keywords</p>
                            <p class="text-2xl font-bold text-[#FFAD00] mt-1">{{ stats.keywords_count }}</p>
                        </div>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-[#FFAD00]/10">
                            <Icon icon="heroicons:hashtag" class="size-5 text-[#FFAD00]" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border shadow-sm">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">Platforms</p>
                            <p class="text-2xl font-bold text-[#a78bfa] mt-1">
                                {{ Object.keys(stats.platforms ?? {}).length }}
                            </p>
                        </div>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-[#a78bfa]/10">
                            <Icon icon="heroicons:squares-plus" class="size-5 text-[#a78bfa]" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── Add keyword panel ── -->
        <Card v-if="addingKeyword" class="border shadow-sm border-primary/30">
            <CardHeader class="pb-3 pt-4 px-4">
                <CardTitle class="text-sm font-semibold flex items-center gap-2">
                    <Icon icon="heroicons:plus-circle" class="size-4 text-primary" />
                    Track a new keyword
                </CardTitle>
            </CardHeader>
            <CardContent class="px-4 pb-4">
                <form class="flex flex-col gap-3" @submit.prevent="submitKeyword">
                    <div class="flex gap-2">
                        <Input
                            v-model="keywordForm.name"
                            placeholder='e.g. "your brand name" or topic…'
                            class="h-9 text-sm flex-1"
                            autofocus
                        />
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="keywordForm.processing || !keywordForm.name.trim()"
                            class="h-9 bg-primary text-primary-foreground hover:opacity-90"
                        >
                            <Icon v-if="keywordForm.processing" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                            <span v-else>Add &amp; Fetch</span>
                        </Button>
                        <Button type="button" variant="ghost" size="sm" class="h-9" @click="addingKeyword = false">
                            Cancel
                        </Button>
                    </div>

                    <div>
                        <p class="text-xs text-muted-foreground mb-1.5">Search on:</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="p in PLATFORM_OPTIONS"
                                :key="p"
                                type="button"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-medium transition-colors"
                                :class="keywordForm.platforms.includes(p)
                                    ? 'bg-primary/15 border-primary/40 text-primary'
                                    : 'bg-muted/30 border-border text-muted-foreground hover:bg-muted/60'"
                                @click="togglePlatform(p)"
                            >
                                <Icon
                                    :icon="platformConfig(p.charAt(0).toUpperCase() + p.slice(1)).icon"
                                    class="size-3"
                                />
                                {{ p }}
                            </button>
                        </div>
                    </div>

                    <p v-if="keywordForm.errors.name" class="text-xs text-destructive">{{ keywordForm.errors.name }}</p>
                </form>
            </CardContent>
        </Card>

        <!-- ── Main split layout ── -->
        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-4 items-start">

            <!-- ── Keywords sidebar ── -->
            <Card class="border shadow-sm">
                <CardHeader class="pb-2 pt-4 px-4">
                    <CardTitle class="text-sm font-semibold">
                        Tracked Keywords
                        <span class="ml-1 text-xs font-normal text-muted-foreground">({{ keywords.length }})</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <!-- Empty state -->
                    <div v-if="keywords.length === 0" class="flex flex-col items-center justify-center py-10 text-center gap-2 px-4">
                        <div class="flex size-12 items-center justify-center rounded-full bg-primary/10">
                            <Icon icon="heroicons:hashtag" class="size-6 text-primary" />
                        </div>
                        <p class="text-sm font-medium text-foreground">No keywords yet</p>
                        <p class="text-xs text-muted-foreground">Click "Add Keyword" to start tracking mentions.</p>
                    </div>

                    <!-- Keywords list -->
                    <ul v-else class="divide-y divide-border">
                        <li
                            v-for="kw in keywords"
                            :key="kw.id"
                            class="px-4 py-3 hover:bg-muted/20 transition-colors"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <button
                                        class="text-sm font-medium text-foreground truncate max-w-full text-left hover:text-primary transition-colors"
                                        :class="{ 'opacity-50 line-through': !kw.is_active }"
                                        @click="keywordId = keywordId == kw.id ? '' : kw.id"
                                    >
                                        {{ kw.name }}
                                    </button>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        {{ kw.mentions_count.toLocaleString() }} mention{{ kw.mentions_count === 1 ? '' : 's' }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-1 shrink-0">
                                    <!-- Fetch now -->
                                    <button
                                        class="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:text-foreground hover:bg-muted/50 transition-colors"
                                        title="Fetch now"
                                        @click="fetchNow(kw)"
                                    >
                                        <Icon icon="heroicons:arrow-path" class="size-3.5" />
                                    </button>
                                    <!-- Active toggle -->
                                    <button
                                        class="flex size-7 items-center justify-center rounded-md transition-colors"
                                        :class="kw.is_active
                                            ? 'text-[#40E0D0] hover:bg-[#40E0D0]/10'
                                            : 'text-muted-foreground hover:bg-muted/50'"
                                        :title="kw.is_active ? 'Pause tracking' : 'Resume tracking'"
                                        @click="toggleActive(kw)"
                                    >
                                        <Icon :icon="kw.is_active ? 'heroicons:pause' : 'heroicons:play'" class="size-3.5" />
                                    </button>
                                    <!-- Notification toggle -->
                                    <button
                                        class="flex size-7 items-center justify-center rounded-md transition-colors"
                                        :class="kw.email_notifications
                                            ? 'text-[#FFAD00] hover:bg-[#FFAD00]/10'
                                            : 'text-muted-foreground hover:bg-muted/50'"
                                        :title="kw.email_notifications ? 'Disable email alerts' : 'Enable email alerts'"
                                        @click="toggleNotifications(kw)"
                                    >
                                        <Icon :icon="kw.email_notifications ? 'heroicons:bell' : 'heroicons:bell-slash'" class="size-3.5" />
                                    </button>
                                    <!-- Delete -->
                                    <button
                                        class="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors"
                                        title="Delete keyword"
                                        @click="deleteKeyword(kw)"
                                    >
                                        <Icon icon="heroicons:trash" class="size-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Platform chips -->
                            <div class="flex flex-wrap gap-1 mt-1.5">
                                <span
                                    v-for="p in (kw.platforms ?? [])"
                                    :key="p"
                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[0.6rem] font-medium"
                                    :style="{
                                        background: platformConfig(p.charAt(0).toUpperCase()+p.slice(1)).bg,
                                        color: platformConfig(p.charAt(0).toUpperCase()+p.slice(1)).color,
                                    }"
                                >
                                    <Icon :icon="platformConfig(p.charAt(0).toUpperCase()+p.slice(1)).icon" class="size-2.5" />
                                    {{ p }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- ── Mentions feed ── -->
            <div class="flex flex-col gap-3">

                <!-- Platform filter tabs + search -->
                <Card class="border shadow-sm">
                    <CardContent class="p-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <!-- Platform tabs -->
                        <div class="flex items-center gap-1 flex-wrap">
                            <button
                                v-for="tab in platformTabs"
                                :key="tab.key"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors"
                                :class="platform === tab.key
                                    ? 'bg-primary/15 text-primary border border-primary/30'
                                    : 'text-muted-foreground hover:bg-muted/50 border border-transparent'"
                                @click="platform = tab.key"
                            >
                                <Icon
                                    v-if="tab.key"
                                    :icon="platformConfig(tab.key).icon"
                                    class="size-3"
                                    :style="{ color: platform === tab.key ? platformConfig(tab.key).color : undefined }"
                                />
                                <Icon v-else icon="heroicons:squares-2x2" class="size-3" />
                                {{ tab.label }}
                                <span class="text-[0.6rem] text-muted-foreground">{{ tab.count.toLocaleString() }}</span>
                            </button>
                        </div>

                        <!-- Search -->
                        <div class="relative shrink-0">
                            <Icon
                                icon="heroicons:magnifying-glass"
                                class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none"
                            />
                            <Input
                                v-model="search"
                                placeholder="Search mentions…"
                                class="h-8 pl-8 text-xs w-full sm:w-52"
                            />
                        </div>
                    </CardContent>
                </Card>

                <!-- Active filters indicator -->
                <div v-if="keywordId" class="flex items-center gap-2">
                    <p class="text-xs text-muted-foreground">
                        Filtered by:
                        <strong class="text-foreground">{{ keywords.find(k => k.id == keywordId)?.name ?? 'keyword' }}</strong>
                    </p>
                    <button class="text-xs text-muted-foreground hover:text-foreground" @click="keywordId = ''">
                        <Icon icon="heroicons:x-mark" class="size-3.5" />
                    </button>
                </div>

                <!-- Empty state -->
                <Card v-if="mentions.data.length === 0" class="border shadow-sm">
                    <CardContent class="flex flex-col items-center justify-center py-16 text-center gap-3">
                        <div class="flex size-14 items-center justify-center rounded-full bg-primary/10">
                            <Icon icon="heroicons:chat-bubble-left-right" class="size-7 text-primary" />
                        </div>
                        <div>
                            <p class="font-semibold text-foreground">
                                {{ keywords.length === 0 ? 'No keywords tracked yet' : 'No mentions found' }}
                            </p>
                            <p class="text-sm text-muted-foreground mt-1 max-w-xs">
                                {{ keywords.length === 0
                                    ? 'Add a keyword above and we\'ll start fetching mentions across all platforms.'
                                    : 'Try changing your filters, or wait for the next fetch cycle.' }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Mention cards -->
                <div v-else class="flex flex-col gap-3">
                    <Card
                        v-for="mention in mentions.data"
                        :key="mention.id"
                        class="border shadow-sm hover:border-border/80 transition-colors"
                    >
                        <CardContent class="p-4">
                            <div class="flex items-start gap-3">
                                <!-- Platform icon -->
                                <div
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg mt-0.5"
                                    :style="{ background: platformConfig(mention.source_type).bg }"
                                >
                                    <Icon
                                        :icon="platformConfig(mention.source_type).icon"
                                        class="size-4.5"
                                        :style="{ color: platformConfig(mention.source_type).color }"
                                    />
                                </div>

                                <div class="flex-1 min-w-0">
                                    <!-- Header row -->
                                    <div class="flex items-center justify-between gap-2 mb-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <Badge
                                                class="text-[0.6rem] px-1.5 py-0 border font-semibold shrink-0"
                                                :style="{
                                                    background: platformConfig(mention.source_type).bg,
                                                    color: platformConfig(mention.source_type).color,
                                                    borderColor: platformConfig(mention.source_type).color + '40',
                                                }"
                                            >
                                                {{ mention.source_type }}
                                            </Badge>
                                            <span
                                                v-if="mention.keyword"
                                                class="text-[0.65rem] text-muted-foreground truncate"
                                            >
                                                #{{ mention.keyword.name }}
                                            </span>
                                        </div>
                                        <span class="text-[0.65rem] text-muted-foreground shrink-0">
                                            {{ fmtDate(mention.posted_at) }}
                                        </span>
                                    </div>

                                    <!-- Title -->
                                    <p
                                        v-if="mention.title"
                                        class="text-sm font-semibold text-foreground leading-snug mb-1"
                                    >
                                        {{ truncate(mention.title, 140) }}
                                    </p>

                                    <!-- Content -->
                                    <p
                                        v-if="mention.content && mention.content !== mention.title"
                                        class="text-xs text-muted-foreground leading-relaxed mb-2"
                                    >
                                        {{ truncate(mention.content, 220) }}
                                    </p>

                                    <!-- Footer row -->
                                    <div class="flex items-center justify-between gap-3 mt-2">
                                        <!-- Author + engagement -->
                                        <div class="flex items-center gap-3 flex-wrap">
                                            <span v-if="mention.username" class="text-xs text-muted-foreground flex items-center gap-1">
                                                <Icon icon="heroicons:user-circle" class="size-3.5" />
                                                {{ mention.username }}
                                            </span>

                                            <span v-if="mention.like_count" class="text-xs text-muted-foreground flex items-center gap-1">
                                                <Icon icon="heroicons:heart" class="size-3.5 text-rose-400" />
                                                {{ fmtNum(mention.like_count) }}
                                            </span>

                                            <span v-if="mention.retweet_count" class="text-xs text-muted-foreground flex items-center gap-1">
                                                <Icon icon="heroicons:arrow-path-rounded-square" class="size-3.5 text-green-400" />
                                                {{ fmtNum(mention.retweet_count) }}
                                            </span>

                                            <span v-if="mention.comments_count" class="text-xs text-muted-foreground flex items-center gap-1">
                                                <Icon icon="heroicons:chat-bubble-oval-left" class="size-3.5 text-blue-400" />
                                                {{ fmtNum(mention.comments_count) }}
                                            </span>

                                            <span v-if="mention.views" class="text-xs text-muted-foreground flex items-center gap-1">
                                                <Icon icon="heroicons:eye" class="size-3.5" />
                                                {{ fmtNum(mention.views) }}
                                            </span>

                                            <span v-if="mention.votes" class="text-xs text-muted-foreground flex items-center gap-1">
                                                <Icon icon="heroicons:arrow-up" class="size-3.5 text-orange-400" />
                                                {{ fmtNum(mention.votes) }}
                                            </span>
                                        </div>

                                        <!-- Reply button -->
                                        <a
                                            v-if="mention.permalink"
                                            :href="mention.permalink"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium border border-border text-muted-foreground hover:text-foreground hover:border-primary/40 hover:bg-primary/5 transition-colors shrink-0"
                                        >
                                            <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                            Reply
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Pagination -->
                <div
                    v-if="mentions.last_page > 1"
                    class="flex items-center justify-between border-t border-border pt-3"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ mentions.from }}–{{ mentions.to }} of {{ mentions.total.toLocaleString() }} mentions
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            v-for="link in mentions.links"
                            :key="link.label"
                            :disabled="!link.url"
                            class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-1.5 text-xs transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            :class="link.active
                                ? 'bg-primary text-primary-foreground border-primary'
                                : 'bg-background text-foreground border-border hover:bg-muted'"
                            @click="link.url && router.get(link.url, {}, { preserveState: true })"
                            v-html="link.label"
                        />
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>
