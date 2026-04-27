<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/* ─── Types ─────────────────────────────────────────────────────────────── */
interface Account {
    id: number;
    provider: string;
    name: string;
    status: string;
    last_connected_at: string | null;
}

interface DispatchLog {
    id: number;
    provider: string;
    status: string;
    attempt: number;
    error_message: string | null;
    funnel_name: string | null;
    created_at: string | null;
}

const props = defineProps<{
    accounts: Account[];
    dispatchLogs: DispatchLog[];
    queueHealth: {
        queued: number;
        failed_last_24h: number;
    };
}>();

/* ─── Provider catalogue ─────────────────────────────────────────────────── */
const PROVIDERS = [
    {
        id: 'mailchimp',
        label: 'Mailchimp',
        icon: 'simple-icons:mailchimp',
        color: '#FFE01B',
        bg: '#1d1d1d',
        desc: 'World\'s largest email platform',
        fields: [
            { key: 'api_key',     label: 'API Key',       type: 'password', hint: 'Found in Account → Extras → API keys' },
            { key: 'audience_id', label: 'Audience ID',   type: 'text',     hint: 'Your list/audience ID from Audience → Settings' },
        ],
    },
    {
        id: 'getresponse',
        label: 'GetResponse',
        icon: 'simple-icons:getresponse',
        color: '#00BAFF',
        bg: '#003865',
        desc: 'Email marketing & automation',
        fields: [
            { key: 'api_key',     label: 'API Key',      type: 'password', hint: 'Profile → Integrations → API' },
            { key: 'campaign_id', label: 'Campaign ID',  type: 'text',     hint: 'The list campaign ID to subscribe leads to' },
        ],
    },
    {
        id: 'convertkit',
        label: 'ConvertKit',
        icon: 'simple-icons:convertkit',
        color: '#FB6970',
        bg: '#1a1a1a',
        desc: 'Creator-focused email platform',
        fields: [
            { key: 'api_secret', label: 'API Secret', type: 'password', hint: 'Account Settings → Advanced → API secret' },
            { key: 'form_id',    label: 'Form ID',    type: 'text',     hint: 'The numeric ID of the form to subscribe to' },
        ],
    },
    {
        id: 'activecampaign',
        label: 'ActiveCampaign',
        icon: 'simple-icons:activecampaign',
        color: '#356AE6',
        bg: '#0b2149',
        desc: 'CRM & email automation',
        fields: [
            { key: 'api_url', label: 'API URL',   type: 'text',     hint: 'e.g. https://youraccountname.api-us1.com' },
            { key: 'api_key', label: 'API Key',   type: 'password', hint: 'Account → Settings → Developer' },
            { key: 'list_id', label: 'List ID',   type: 'text',     hint: 'Numeric ID of the contact list' },
        ],
    },
    {
        id: 'sendinblue',
        label: 'Brevo (Sendinblue)',
        icon: 'simple-icons:sendinblue',
        color: '#0092FF',
        bg: '#001c37',
        desc: 'All-in-one marketing platform',
        fields: [
            { key: 'api_key', label: 'API Key',  type: 'password', hint: 'Account → SMTP & API → API Keys' },
            { key: 'list_id', label: 'List ID',  type: 'text',     hint: 'Numeric ID of the contact list' },
        ],
    },
    {
        id: 'generic_webhook',
        label: 'Generic Webhook',
        icon: 'heroicons:bolt',
        color: '#40E0D0',
        bg: '#00201d',
        desc: 'POST leads to any URL',
        fields: [
            { key: 'webhook_url', label: 'Webhook URL', type: 'url',      hint: 'URL that receives a JSON POST on each opt-in' },
            { key: 'api_key',     label: 'Auth Token',  type: 'password', hint: 'Optional — sent as Authorization: Bearer header' },
        ],
    },
] as const;

type ProviderId = (typeof PROVIDERS)[number]['id'];

/* ─── State ──────────────────────────────────────────────────────────────── */
const showForm      = ref(false);
const selectedId    = ref<ProviderId | null>(null);
const testResults   = ref<Record<number, { ok: boolean; message: string } | null>>({});
const testingId     = ref<number | null>(null);
const deletingId    = ref<number | null>(null);

const selectedProvider = computed(() =>
    PROVIDERS.find((p) => p.id === selectedId.value) ?? null,
);

const form = useForm<{
    provider: string;
    name: string;
    credentials: Record<string, string>;
    config: Record<string, never>;
}>({
    provider: '',
    name: '',
    credentials: {},
    config: {},
});

/* ─── Helpers ────────────────────────────────────────────────────────────── */
function selectProvider(id: ProviderId): void {
    selectedId.value = id;
    form.provider = id;
    form.credentials = {};
    form.name = '';
}

function openForm(): void {
    showForm.value = true;
    selectedId.value = null;
    form.reset();
}

function cancelForm(): void {
    showForm.value = false;
    selectedId.value = null;
    form.reset();
}

function submit(): void {
    form.post('/integrations', {
        onSuccess: () => {
            showForm.value = false;
            selectedId.value = null;
            form.reset();
        },
    });
}

async function testConnection(account: Account): Promise<void> {
    testingId.value = account.id;
    testResults.value[account.id] = null;

    try {
        const res = await fetch(`/integrations/${account.id}/test`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
        });
        const data = (await res.json()) as { ok: boolean; message: string };
        testResults.value[account.id] = data;
    } catch {
        testResults.value[account.id] = { ok: false, message: 'Network error.' };
    } finally {
        testingId.value = null;
    }
}

function deleteAccount(account: Account): void {
    if (!confirm(`Remove "${account.name}"? This cannot be undone.`)) {
        return;
    }

    deletingId.value = account.id;
    router.delete(`/integrations/${account.id}`, {
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

function providerMeta(id: string) {
    return PROVIDERS.find((p) => p.id === id) ?? {
        label: id,
        icon: 'heroicons:envelope',
        color: '#40E0D0',
        bg: '#111',
    };
}

function formatDate(dt: string | null): string {
    if (!dt) {
        return 'Never';
    }

    return new Date(dt).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateTime(dt: string | null): string {
    if (!dt) {
        return 'Unknown';
    }

    return new Date(dt).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

const activeCount = computed(() => props.accounts.filter((a) => a.status === 'active').length);
</script>

<template>
    <Head title="ESP Integrations" />

    <div class="flex flex-col gap-6 p-4 md:p-6 w-full max-w-screen-xl mx-auto">

            <!-- ── Page header ── -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-foreground">ESP Integrations</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">
                        Connect your email service provider — leads captured on opt-in pages are automatically subscribed.
                    </p>
                </div>

                <Button
                    size="sm"
                    class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90 shrink-0 self-start"
                    @click="openForm"
                >
                    <Icon icon="heroicons:plus" class="size-4" />
                    Connect Integration
                </Button>
            </div>

            <!-- ── Stats ── -->
            <div class="grid grid-cols-3 gap-3">
                <Card class="border shadow-sm">
                    <CardContent class="p-4">
                        <p class="text-xs text-muted-foreground">Connected</p>
                        <p class="text-2xl font-bold text-foreground mt-1">{{ accounts.length }}</p>
                    </CardContent>
                </Card>
                <Card class="border shadow-sm">
                    <CardContent class="p-4">
                        <p class="text-xs text-muted-foreground">Active</p>
                        <p class="text-2xl font-bold text-emerald-500 mt-1">{{ activeCount }}</p>
                    </CardContent>
                </Card>
                <Card class="border shadow-sm">
                    <CardContent class="p-4">
                        <p class="text-xs text-muted-foreground">Providers</p>
                        <p class="text-2xl font-bold text-foreground mt-1">{{ PROVIDERS.length }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Add integration panel ── -->
            <Card v-if="showForm" class="border-2 border-primary/30 shadow-md">
                <CardHeader class="pb-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-base font-semibold">Connect a new integration</CardTitle>
                            <CardDescription class="text-xs">Select a provider, then enter your credentials</CardDescription>
                        </div>
                        <Button variant="ghost" size="sm" class="h-7 w-7 p-0" @click="cancelForm">
                            <Icon icon="heroicons:x-mark" class="size-4" />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-5">

                    <!-- Step 1: Provider grid -->
                    <div>
                        <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-3">
                            1 — Choose provider
                        </p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2">
                            <button
                                v-for="p in PROVIDERS"
                                :key="p.id"
                                type="button"
                                class="group relative flex flex-col items-center gap-1.5 rounded-xl border p-3 text-center transition-all hover:border-primary/60 hover:shadow-sm"
                                :class="selectedId === p.id
                                    ? 'border-primary bg-primary/5 shadow-sm ring-1 ring-primary/30'
                                    : 'border-border bg-card'"
                                @click="selectProvider(p.id as ProviderId)"
                            >
                                <div
                                    class="flex size-9 items-center justify-center rounded-lg"
                                    :style="{ background: p.bg }"
                                >
                                    <Icon :icon="p.icon" class="size-5" :style="{ color: p.color }" />
                                </div>
                                <span class="text-[0.7rem] font-medium leading-tight text-foreground">{{ p.label }}</span>
                                <div
                                    v-if="selectedId === p.id"
                                    class="absolute -top-1.5 -right-1.5 flex size-4 items-center justify-center rounded-full bg-primary"
                                >
                                    <Icon icon="heroicons:check" class="size-2.5 text-white" />
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Credential form (only when provider selected) -->
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        leave-active-class="transition-all duration-150 ease-in"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <form v-if="selectedProvider" class="space-y-4 pt-2" @submit.prevent="submit">
                            <div class="flex items-center gap-2 pb-2 border-b">
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg"
                                    :style="{ background: selectedProvider.bg }"
                                >
                                    <Icon :icon="selectedProvider.icon" class="size-4.5" :style="{ color: selectedProvider.color }" />
                                </div>
                                <div>
                                    <p class="text-sm font-semibold">{{ selectedProvider.label }}</p>
                                    <p class="text-xs text-muted-foreground">{{ selectedProvider.desc }}</p>
                                </div>
                            </div>

                            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                                2 — Enter credentials
                            </p>

                            <!-- Account nickname -->
                            <div class="space-y-1">
                                <Label class="text-xs">Account nickname <span class="text-destructive">*</span></Label>
                                <Input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="e.g. My Mailchimp Account"
                                    class="h-9 text-sm"
                                    required
                                />
                                <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                            </div>

                            <!-- Dynamic credential fields -->
                            <div
                                v-for="field in selectedProvider.fields"
                                :key="field.key"
                                class="space-y-1"
                            >
                                <Label class="text-xs">
                                    {{ field.label }} <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    v-model="form.credentials[field.key]"
                                    :type="field.type"
                                    :placeholder="field.label"
                                    class="h-9 text-sm font-mono"
                                    required
                                />
                                <p class="text-[0.68rem] text-muted-foreground">{{ field.hint }}</p>
                                <p
                                    v-if="(form.errors as Record<string, string>)[`credentials.${field.key}`]"
                                    class="text-xs text-destructive"
                                >
                                    {{ (form.errors as Record<string, string>)[`credentials.${field.key}`] }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 pt-1">
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                                    :disabled="form.processing"
                                >
                                    <Icon
                                        v-if="form.processing"
                                        icon="heroicons:arrow-path"
                                        class="size-3.5 animate-spin"
                                    />
                                    <Icon v-else icon="heroicons:check-circle" class="size-3.5" />
                                    {{ form.processing ? 'Saving…' : 'Save Integration' }}
                                </Button>
                                <Button type="button" variant="outline" size="sm" @click="cancelForm">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </Transition>
                </CardContent>
            </Card>

            <!-- ── Connected integrations ── -->
            <div>
                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-3">
                    Connected accounts ({{ accounts.length }})
                </p>

                <!-- Empty state -->
                <Card v-if="accounts.length === 0" class="border border-dashed shadow-none">
                    <CardContent class="flex flex-col items-center justify-center py-14 text-center gap-3">
                        <div class="flex size-14 items-center justify-center rounded-full bg-primary/10">
                            <Icon icon="heroicons:puzzle-piece" class="size-7 text-primary" />
                        </div>
                        <div>
                            <p class="font-semibold text-foreground">No integrations connected</p>
                            <p class="text-sm text-muted-foreground mt-1">
                                Connect an ESP to automatically push leads to your email list.
                            </p>
                        </div>
                        <Button
                            size="sm"
                            class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90 mt-2"
                            @click="openForm"
                        >
                            <Icon icon="heroicons:plus" class="size-4" />
                            Connect your first integration
                        </Button>
                    </CardContent>
                </Card>

                <!-- Account cards -->
                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="account in accounts"
                        :key="account.id"
                        class="border shadow-sm overflow-hidden"
                    >
                        <CardContent class="p-0">
                            <!-- Provider colour header -->
                            <div
                                class="flex items-center gap-3 px-4 py-3"
                                :style="{ background: providerMeta(account.provider).bg }"
                            >
                                <div class="flex size-9 items-center justify-center rounded-lg bg-black/20 shrink-0">
                                    <Icon
                                        :icon="providerMeta(account.provider).icon"
                                        class="size-5"
                                        :style="{ color: providerMeta(account.provider).color }"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-white truncate">{{ account.name }}</p>
                                    <p class="text-[0.7rem] text-white/60">{{ providerMeta(account.provider).label }}</p>
                                </div>
                                <Badge
                                    class="shrink-0 text-[0.65rem] capitalize px-1.5"
                                    :class="account.status === 'active'
                                        ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                                        : 'bg-red-500/20 text-red-300 border-red-500/30'"
                                >
                                    {{ account.status }}
                                </Badge>
                            </div>

                            <!-- Info & actions -->
                            <div class="px-4 py-3 space-y-3">
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Icon icon="heroicons:clock" class="size-3.5" />
                                    Last tested {{ formatDate(account.last_connected_at) }}
                                </div>

                                <!-- Test result feedback -->
                                <div
                                    v-if="testResults[account.id]"
                                    class="flex items-start gap-1.5 rounded-md border px-2.5 py-2 text-xs"
                                    :class="testResults[account.id]?.ok
                                        ? 'border-emerald-500/30 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400'
                                        : 'border-destructive/30 bg-destructive/5 text-destructive'"
                                >
                                    <Icon
                                        :icon="testResults[account.id]?.ok ? 'heroicons:check-circle' : 'heroicons:x-circle'"
                                        class="size-3.5 shrink-0 mt-0.5"
                                    />
                                    {{ testResults[account.id]?.message }}
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-7 text-xs gap-1 flex-1"
                                        :disabled="testingId === account.id"
                                        @click="testConnection(account)"
                                    >
                                        <Icon
                                            :icon="testingId === account.id ? 'heroicons:arrow-path' : 'heroicons:signal'"
                                            class="size-3"
                                            :class="testingId === account.id ? 'animate-spin' : ''"
                                        />
                                        {{ testingId === account.id ? 'Testing…' : 'Test' }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        class="h-7 text-xs gap-1 text-destructive hover:text-destructive border-destructive/30 hover:bg-destructive/5"
                                        :disabled="deletingId === account.id"
                                        @click="deleteAccount(account)"
                                    >
                                        <Icon
                                            :icon="deletingId === account.id ? 'heroicons:arrow-path' : 'heroicons:trash'"
                                            class="size-3"
                                            :class="deletingId === account.id ? 'animate-spin' : ''"
                                        />
                                        Remove
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- ── Provider reference ── -->
            <Card class="border shadow-sm">
                <CardHeader class="pb-3">
                    <CardTitle class="text-sm font-semibold">Dispatch Monitor</CardTitle>
                    <CardDescription class="text-xs">Track queued, successful, and failed ESP deliveries in real time.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="rounded-lg border bg-muted/20 p-3 text-xs">
                        <p class="font-semibold text-foreground">Queue worker reminder</p>
                        <p class="mt-1 text-muted-foreground">
                            Run <code class="font-mono text-[11px]">php artisan queue:work</code> in production so queued lead sync jobs are processed.
                        </p>
                        <div class="mt-2 flex items-center gap-2">
                            <Badge class="bg-amber-50 text-amber-700 border-amber-200">Queued: {{ queueHealth.queued }}</Badge>
                            <Badge
                                :class="queueHealth.failed_last_24h > 0
                                    ? 'bg-red-50 text-red-700 border-red-200'
                                    : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                            >
                                Failed (24h): {{ queueHealth.failed_last_24h }}
                            </Badge>
                        </div>
                    </div>

                    <div v-if="dispatchLogs.length === 0" class="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                        No dispatch activity yet.
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="log in dispatchLogs"
                            :key="log.id"
                            class="rounded-lg border p-3"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-xs font-semibold text-foreground">
                                        {{ providerMeta(log.provider).label }} · {{ log.funnel_name ?? 'Unknown funnel' }}
                                    </p>
                                    <p class="text-[0.68rem] text-muted-foreground mt-0.5">
                                        {{ formatDateTime(log.created_at) }} · Attempt {{ log.attempt }}
                                    </p>
                                </div>
                                <Badge
                                    class="capitalize text-[0.62rem] px-1.5 py-0.5"
                                    :class="log.status === 'success'
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                        : log.status === 'queued'
                                            ? 'bg-amber-50 text-amber-700 border-amber-200'
                                            : 'bg-red-50 text-red-700 border-red-200'"
                                >
                                    {{ log.status }}
                                </Badge>
                            </div>
                            <p v-if="log.error_message" class="mt-2 text-xs text-destructive">{{ log.error_message }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border shadow-sm">
                <CardHeader class="pb-3">
                    <CardTitle class="text-sm font-semibold">Supported Providers</CardTitle>
                    <CardDescription class="text-xs">All providers use API key authentication — no OAuth redirects required.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                        <div
                            v-for="p in PROVIDERS"
                            :key="p.id"
                            class="flex items-center gap-2.5 rounded-lg border bg-card px-3 py-2"
                        >
                            <div
                                class="flex size-7 items-center justify-center rounded-md shrink-0"
                                :style="{ background: p.bg }"
                            >
                                <Icon :icon="p.icon" class="size-3.5" :style="{ color: p.color }" />
                            </div>
                            <span class="text-xs font-medium truncate text-foreground">{{ p.label }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

        </div>
</template>
