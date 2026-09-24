<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import TrackedLinkAnalyticsDialog from '@/components/tracked-links/TrackedLinkAnalyticsDialog.vue';
import TrackedLinkRuleEditor from '@/components/tracked-links/TrackedLinkRuleEditor.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    payloadToRules,
    rulesToPayload,
    type DeviceRule,
    type GeoRule,
} from '@/composables/useTrackedLinkRules';
import { countryLabel } from '@/data/trackedLinkCountries';

type TrackedLinkRow = {
    id: number;
    code: string;
    label: string | null;
    destination_url: string;
    click_count: number;
    public_url: string;
    is_active: boolean;
    geo_rules: Record<string, string> | null;
    device_rules: Record<string, string> | null;
    created_at: string;
};

const props = defineProps<{ links: TrackedLinkRow[]; clicks_last_7_days?: number }>();

const createGeoRules = ref<GeoRule[]>([]);
const createDeviceRules = ref<DeviceRule[]>([]);

const form = useForm({
    label: '',
    destination_url: '',
});

const editOpen = ref(false);
const editing = ref<TrackedLinkRow | null>(null);
const editGeoRules = ref<GeoRule[]>([]);
const editDeviceRules = ref<DeviceRule[]>([]);
const editForm = useForm({
    label: '',
    destination_url: '',
    is_active: true,
});

const analyticsOpen = ref(false);
const analyticsLinkId = ref<number | null>(null);
const search = ref('');

const stats = computed(() => {
    const active = props.links.filter((l) => l.is_active).length;
    const clicks = props.links.reduce((sum, l) => sum + l.click_count, 0);
    const withRules = props.links.filter((l) =>
        (l.geo_rules && Object.keys(l.geo_rules).length > 0)
        || (l.device_rules && Object.keys(l.device_rules).length > 0),
    ).length;

    return { total: props.links.length, active, clicks, withRules };
});

const statCards = computed(() => [
    { label: 'Total', value: stats.value.total, sub: 'links', icon: 'heroicons:link' },
    { label: 'Active', value: stats.value.active, sub: 'live', icon: 'heroicons:signal' },
    { label: 'Clicks', value: stats.value.clicks, sub: 'all time', icon: 'heroicons:cursor-arrow-rays' },
    { label: 'This week', value: props.clicks_last_7_days ?? 0, sub: 'clicks', icon: 'heroicons:chart-bar' },
]);

const filteredLinks = computed(() => {
    if (!search.value.trim()) return props.links;
    const q = search.value.toLowerCase();
    return props.links.filter(
        (l) =>
            (l.label?.toLowerCase().includes(q) ?? false)
            || l.code.toLowerCase().includes(q)
            || l.public_url.toLowerCase().includes(q)
            || l.destination_url.toLowerCase().includes(q),
    );
});

function submitCreate() {
    const rules = rulesToPayload(createGeoRules.value, createDeviceRules.value);
    form
        .transform((data) => ({
            label: data.label,
            destination_url: data.destination_url,
            ...rules,
        }))
        .post('/tracked-links', {
            onSuccess: () => {
                form.reset();
                createGeoRules.value = [];
                createDeviceRules.value = [];
                toast.success('Cloaked link created.');
            },
        });
}

function openEdit(link: TrackedLinkRow) {
    editing.value = link;
    const parsed = payloadToRules(link.geo_rules, link.device_rules);
    editGeoRules.value = parsed.geoRules;
    editDeviceRules.value = parsed.deviceRules;
    editForm.label = link.label ?? '';
    editForm.destination_url = link.destination_url;
    editForm.is_active = link.is_active;
    editOpen.value = true;
}

function submitEdit() {
    if (!editing.value) return;

    const rules = rulesToPayload(editGeoRules.value, editDeviceRules.value);
    editForm
        .transform((data) => ({
            label: data.label,
            destination_url: data.destination_url,
            is_active: data.is_active,
            ...rules,
        }))
        .patch(`/tracked-links/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                editOpen.value = false;
                toast.success('Link updated.');
            },
        });
}

function toggleActive(link: TrackedLinkRow, active: boolean) {
    router.patch(`/tracked-links/${link.id}`, { is_active: active }, { preserveScroll: true });
}

function openAnalytics(link: TrackedLinkRow) {
    analyticsLinkId.value = link.id;
    analyticsOpen.value = true;
}

async function copyUrl(url: string) {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Copied to clipboard.');
    } catch {
        toast.error('Could not copy URL.');
    }
}

function deleteLink(id: number, label: string | null) {
    const name = label || 'this link';
    if (!window.confirm(`Delete ${name}? This cannot be undone.`)) return;

    router.delete(`/tracked-links/${id}`, { preserveScroll: true });
}

function ruleSummary(link: TrackedLinkRow): string[] {
    const parts: string[] = [];
    const geoCount = link.geo_rules ? Object.keys(link.geo_rules).length : 0;
    const deviceCount = link.device_rules ? Object.keys(link.device_rules).length : 0;
    if (geoCount) parts.push(`${geoCount} geo`);
    if (deviceCount) parts.push(`${deviceCount} device`);
    return parts;
}

function fmtDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

<template>
    <Head title="Tracked Links" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Link cloaking</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Cloak affiliate URLs with geo and device routing. Campaign CTAs are auto-tracked on each campaign’s Publish step.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button as-child variant="brand-outline" size="sm">
                    <Link href="/campaigns">
                        <Icon icon="heroicons:rocket-launch" class="size-3.5" />
                        All campaigns
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-2 md:grid-cols-4 md:gap-3">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="flex items-center gap-3 rounded-xl border border-border/60 bg-white px-3 py-2.5 shadow-sm"
            >
                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                    <Icon :icon="stat.icon" class="size-4 text-blue-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">{{ stat.label }}</p>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-xl font-bold leading-none tabular-nums">{{ stat.value }}</span>
                        <span class="text-[0.65rem] text-muted-foreground">{{ stat.sub }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create form -->
        <Card class="border border-border/60 bg-white shadow-sm">
            <CardHeader class="pb-3">
                <CardTitle class="text-base">Create cloaked link</CardTitle>
                <CardDescription>Paste your hop link — optional geo and device rules route visitors by country or device.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="space-y-2 sm:col-span-2">
                        <Label>Destination URL</Label>
                        <Input v-model="form.destination_url" placeholder="https://your-affiliate-hop-link…" />
                    </div>
                    <div class="space-y-2 sm:col-span-2">
                        <Label>Label</Label>
                        <Input v-model="form.label" placeholder="Main offer hop" />
                    </div>
                </div>
                <TrackedLinkRuleEditor
                    v-model:geo-rules="createGeoRules"
                    v-model:device-rules="createDeviceRules"
                />
                <Button variant="brand" :disabled="form.processing || !form.destination_url" @click="submitCreate">
                    <Icon icon="heroicons:plus" class="size-3.5" />
                    Create cloaked link
                </Button>
            </CardContent>
        </Card>

        <!-- Search (when links exist) -->
        <div
            v-if="links.length > 0"
            class="rounded-xl border border-border/60 bg-white p-3 shadow-sm md:p-4"
        >
            <div class="relative max-w-md">
                <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Search by label, code, or URL…" class="pl-9" />
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="!links.length"
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-blue-200/60 bg-white px-6 py-14 text-center shadow-sm"
        >
            <div class="flex size-14 items-center justify-center rounded-2xl bg-blue-500/10">
                <Icon icon="heroicons:link" class="size-7 text-blue-600/60" />
            </div>
            <div>
                <p class="font-semibold text-foreground">No manual links yet</p>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    Create one above — campaign links also appear on each campaign’s Publish step.
                </p>
            </div>
        </div>

        <!-- No search results -->
        <div
            v-else-if="filteredLinks.length === 0"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/60 bg-white px-6 py-12 text-center shadow-sm"
        >
            <Icon icon="heroicons:magnifying-glass" class="size-8 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">No links match your search.</p>
        </div>

        <!-- Link list -->
        <div v-else class="grid gap-3">
            <article
                v-for="link in filteredLinks"
                :key="link.id"
                class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm transition-all hover:border-blue-200/60 hover:shadow-md"
            >
                <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-blue-500/15 bg-blue-500/10">
                            <Icon icon="heroicons:shield-check" class="size-5 text-blue-600" />
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-sm">{{ link.label || link.code }}</span>
                                <Badge
                                    variant="outline"
                                    class="text-[0.6rem]"
                                    :class="link.is_active ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                >
                                    {{ link.is_active ? 'Active' : 'Paused' }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="text-[0.6rem] tabular-nums"
                                    :class="link.click_count > 0 ? 'border-blue-200 bg-blue-50 text-blue-700' : ''"
                                >
                                    {{ link.click_count }} {{ link.click_count === 1 ? 'click' : 'clicks' }}
                                </Badge>
                                <Badge v-for="tag in ruleSummary(link)" :key="tag" variant="outline" class="text-[0.6rem]">
                                    {{ tag }}
                                </Badge>
                            </div>
                            <a
                                :href="link.public_url"
                                class="block break-all text-sm text-blue-600 hover:text-blue-800 hover:underline"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ link.public_url }}
                            </a>
                            <p class="break-all text-xs text-muted-foreground">→ {{ link.destination_url }}</p>
                            <p class="text-[0.65rem] text-muted-foreground">
                                <Icon icon="heroicons:calendar" class="mr-1 inline size-3" />
                                Created {{ fmtDate(link.created_at) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2 lg:flex-col lg:items-stretch xl:flex-row">
                        <div class="flex items-center gap-2 rounded-lg border border-border/60 bg-muted/20 px-2.5 py-1.5">
                            <Label class="text-xs text-muted-foreground">Active</Label>
                            <Switch :checked="link.is_active" @update:checked="toggleActive(link, $event)" />
                        </div>
                        <Button type="button" variant="brand-outline" size="sm" @click="copyUrl(link.public_url)">
                            <Icon icon="heroicons:clipboard-document" class="size-3.5" />
                            Copy
                        </Button>
                        <Button type="button" variant="brand-outline" size="sm" @click="openAnalytics(link)">
                            <Icon icon="heroicons:chart-bar" class="size-3.5" />
                            Analytics
                        </Button>
                        <Button type="button" variant="brand" size="sm" @click="openEdit(link)">
                            <Icon icon="heroicons:pencil-square" class="size-3.5" />
                            Edit
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-muted-foreground hover:text-destructive"
                            @click="deleteLink(link.id, link.label)"
                        >
                            <Icon icon="heroicons:trash" class="size-3.5" />
                        </Button>
                    </div>
                </div>

                <div
                    v-if="link.geo_rules || link.device_rules"
                    class="grid gap-2 border-t border-border/60 bg-muted/10 px-4 py-3 text-xs sm:grid-cols-2"
                >
                    <div v-if="link.geo_rules" class="rounded-lg border border-border/60 bg-white p-2.5">
                        <p class="font-medium text-muted-foreground">Geo rules</p>
                        <p v-for="(url, country) in link.geo_rules" :key="country" class="mt-1 break-all">
                            <span class="font-mono text-blue-700">{{ countryLabel(String(country)) }} ({{ country }})</span> → {{ url }}
                        </p>
                    </div>
                    <div v-if="link.device_rules" class="rounded-lg border border-border/60 bg-white p-2.5">
                        <p class="font-medium text-muted-foreground">Device rules</p>
                        <p v-for="(url, device) in link.device_rules" :key="device" class="mt-1 break-all capitalize">
                            {{ device }} → {{ url }}
                        </p>
                    </div>
                </div>
            </article>
        </div>

        <Dialog v-model:open="editOpen">
            <DialogContent class="max-h-[90vh] max-w-xl overflow-y-auto rounded-xl border border-border/60">
                <DialogHeader>
                    <DialogTitle>Edit cloaked link</DialogTitle>
                    <DialogDescription>Update destination, routing rules, or pause the link.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label>Label</Label>
                        <Input v-model="editForm.label" />
                    </div>
                    <div class="space-y-2">
                        <Label>Destination URL</Label>
                        <Input v-model="editForm.destination_url" />
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch v-model:checked="editForm.is_active" />
                        <Label>Link is active</Label>
                    </div>
                    <TrackedLinkRuleEditor
                        v-model:geo-rules="editGeoRules"
                        v-model:device-rules="editDeviceRules"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="editOpen = false">Cancel</Button>
                    <Button variant="brand" :disabled="editForm.processing" @click="submitEdit">Save changes</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <TrackedLinkAnalyticsDialog v-model:open="analyticsOpen" :link-id="analyticsLinkId" />
    </div>
</template>
