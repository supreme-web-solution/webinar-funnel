<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';
import { nextTick, onMounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    funnel: {
        id: number;
        name: string;
        slug: string;
        status: string;
        settings: {
            webinar_title?: string | null;
            webinar_description?: string | null;
            video_url?: string | null;
            chat_mode: string;
            allow_replay: boolean;
            double_opt_in: boolean;
            chat_seed_messages?: Array<{ author: string; message: string }>;
        } | null;
        pages: Array<{
            page_type: 'optin' | 'webinar';
            schema: Record<string, unknown>;
        }>;
        integrations: Array<{ integration_account: { id: number; name: string; provider: string } }>;
    };
    integrationAccounts: Array<{ id: number; name: string; provider: string }>;
    conversationSummaries: Array<{
        conversation_key: string;
        attendee_name: string;
        attendee_email?: string | null;
        latest_message?: string | null;
        message_count: number;
    }>;
    publicLinks: {
        optin: string;
        webinar: string;
    };
}>();

const optinPage = props.funnel.pages.find((p) => p.page_type === 'optin');
const editorContainer = ref<HTMLElement | null>(null);
const copiedLink = ref<'optin' | 'webinar' | null>(null);
const savingPage = ref(false);
const savingSettings = ref(false);
const publishing = ref(false);
const activeTab = ref('optin');

/*
 * When the user navigates away from the optin tab and back, GrapesJS's
 * internal iframe is painted at 0×0 while the panel is hidden. Calling
 * editor.refresh() after the next DOM tick forces a full canvas repaint.
 */
watch(activeTab, (tab) => {
    if (tab !== 'optin') {
        return;
    }

    nextTick(() => {
        const editor = editorContainer.value && (editorContainer.value as any).__gjsEditor;

        if (editor) {
            editor.refresh();
        }
    });
});

const pageForm = useForm<{ page_type: 'optin' | 'webinar'; schema: any }>({
    page_type: 'optin',
    schema: optinPage?.schema ?? {},
});

const publishForm = useForm({});

const settingsForm = useForm<{
    webinar_title: string;
    webinar_description: string;
    video_url: string;
    chat_mode: string;
    allow_replay: boolean;
    double_opt_in: boolean;
    chat_seed_messages: Array<{ author: string; message: string }>;
    branding: { primary: string; secondary: string };
    integration_account_ids: number[];
}>({
    webinar_title: props.funnel.settings?.webinar_title ?? '',
    webinar_description: props.funnel.settings?.webinar_description ?? '',
    video_url: props.funnel.settings?.video_url ?? '',
    chat_mode: props.funnel.settings?.chat_mode ?? 'simulated',
    allow_replay: props.funnel.settings?.allow_replay ?? true,
    double_opt_in: props.funnel.settings?.double_opt_in ?? false,
    chat_seed_messages: props.funnel.settings?.chat_seed_messages ?? [],
    branding: { primary: '#111827', secondary: '#F9FAFB' },
    integration_account_ids: props.funnel.integrations.map((i) => i.integration_account.id),
});

const savePage = (): void => {
    if (editorContainer.value && (editorContainer.value as any).__gjsEditor) {
        const editor = (editorContainer.value as any).__gjsEditor;

        /*
         * Only store plain strings — never pass GrapesJS component/style
         * manager objects into the form, as their internal reactive proxies
         * cause Inertia's hasFiles() serialiser to overflow the call stack.
         */
        pageForm.schema = {
            html: String(editor.getHtml()),
            css:  String(editor.getCss()),
        };
    }

    savingPage.value = true;
    pageForm.patch(`/funnels/${props.funnel.id}/pages`, {
        onFinish: () => {
            savingPage.value = false;
        },
    });
};

const saveSettings = (): void => {
    savingSettings.value = true;
    settingsForm.patch(`/funnels/${props.funnel.id}/settings`, {
        onFinish: () => {
            savingSettings.value = false;
        },
    });
};

const publish = (): void => {
    publishing.value = true;
    publishForm.post(`/funnels/${props.funnel.id}/publish`, {
        onFinish: () => {
            publishing.value = false;
        },
    });
};

const copyLink = async (type: 'optin' | 'webinar'): Promise<void> => {
    await navigator.clipboard.writeText(props.publicLinks[type]);
    copiedLink.value = type;
    setTimeout(() => {
        copiedLink.value = null;
    }, 2000);
};

const espProviderIcon: Record<string, string> = {
    mailchimp: 'simple-icons:mailchimp',
    getresponse: 'simple-icons:getresponse',
    activecampaign: 'simple-icons:activecampaign',
    convertkit: 'simple-icons:convertkit',
    aweber: 'logos:aweber',
    drip: 'simple-icons:drip',
};

function providerIcon(provider: string): string {
    return espProviderIcon[provider.toLowerCase()] ?? 'heroicons:envelope';
}

onMounted(() => {
    if (!editorContainer.value) {
        return;
    }

    const schema = pageForm.schema as any;

    /* Use saved GrapesJS HTML, or fall back to the template default HTML */
    const initialHtml = schema?.html ?? `
<div class="dfy-page">
  <section class="dfy-hero">
    <div class="dfy-inner">
      <span class="dfy-badge">🎓 FREE WEBINAR</span>
      <h1 class="dfy-headline">${schema?.hero?.headline ?? 'Your Webinar Headline Here'}</h1>
      <p class="dfy-sub">${schema?.hero?.subheadline ?? 'Register below to secure your free spot.'}</p>
      <form class="dfy-form" data-locked-form="true">
        <input class="dfy-input" name="name" type="text" placeholder="Your full name" required />
        <input class="dfy-input" name="email" type="email" placeholder="Your best email" required />
        <button class="dfy-btn" type="submit">${schema?.hero?.cta ?? 'Reserve My Spot →'}</button>
      </form>
    </div>
  </section>
</div>`;

    /* Use saved GrapesJS CSS, or fall back to a basic starter style */
    const initialCss = schema?.css ?? `
.dfy-page{min-height:100vh;background:linear-gradient(140deg,#060d1a 0%,#0d2039 100%);display:flex;align-items:center;justify-content:center;padding:40px 16px}
.dfy-inner{max-width:520px;width:100%;text-align:center}
.dfy-badge{display:inline-block;background:rgba(255,80,80,.15);border:1px solid rgba(255,80,80,.3);color:#ff7070;padding:6px 16px;border-radius:100px;font-size:11px;font-weight:700;margin-bottom:24px}
.dfy-headline{font-size:2.2rem;font-weight:900;color:#fff;line-height:1.2;margin-bottom:14px}
.dfy-sub{font-size:1rem;color:rgba(255,255,255,.6);line-height:1.7;margin-bottom:28px}
.dfy-form{background:rgba(255,255,255,.05);border:1px solid rgba(64,224,208,.2);border-radius:16px;padding:28px}
.dfy-input{display:block;width:100%;padding:13px 16px;border:1px solid rgba(255,255,255,.15);border-radius:8px;background:rgba(255,255,255,.07);color:#fff;font-size:15px;outline:none;margin-bottom:12px}
.dfy-input::placeholder{color:rgba(255,255,255,.35)}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#40E0D0,#2dc4b5);color:#060d1a;font-size:16px;font-weight:800;border:none;border-radius:8px;cursor:pointer}`;

    const editor = grapesjs.init({
        container: editorContainer.value,
        fromElement: false,
        height: '480px',
        storageManager: false,
        components: initialHtml,
        style: initialCss,
    });

    editor.on('component:remove:before', (component: any, remove: () => void, opts: any) => {
        if (component?.getAttributes()?.['data-locked-form']) {
            opts.abort = true;
        }
    });

    (editorContainer.value as any).__gjsEditor = editor;
});
</script>

<template>
    <Head :title="`Edit — ${funnel.name}`" />

    <div class="flex flex-col gap-5 p-4 md:p-6 w-full max-w-screen-xl mx-auto">

        <!-- ── Page header ── -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3 min-w-0">
                <Button as-child variant="ghost" size="sm" class="shrink-0 text-muted-foreground h-8 px-2 -ml-1 mt-0.5">
                    <Link href="/dashboard">
                        <Icon icon="heroicons:arrow-left" class="size-4" />
                    </Link>
                </Button>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl font-bold tracking-tight text-foreground truncate">{{ funnel.name }}</h1>
                        <Badge
                            class="capitalize text-[0.65rem] px-2 py-0.5 shrink-0"
                            :class="funnel.status === 'published'
                                ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                : 'bg-amber-50 text-amber-700 border-amber-200'"
                        >
                            <span
                                v-if="funnel.status === 'published'"
                                class="mr-1 inline-block size-1.5 rounded-full bg-emerald-500"
                            />
                            {{ funnel.status }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">/{{ funnel.slug }}</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto">
                <Button as-child variant="outline" size="sm" class="h-8 text-xs gap-1.5">
                    <a :href="`/funnels/${funnel.id}/chat`">
                        <Icon icon="heroicons:chat-bubble-left-right" class="size-3.5" />
                        Chat Manager
                    </a>
                </Button>
                <Button
                    size="sm"
                    class="h-8 text-xs gap-1.5 font-semibold"
                    :class="funnel.status === 'published'
                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                        : 'bg-primary text-primary-foreground hover:opacity-90'"
                    :disabled="publishing"
                    @click="publish"
                >
                    <Icon
                        v-if="publishing"
                        icon="heroicons:arrow-path"
                        class="size-3.5 animate-spin"
                    />
                    <Icon v-else icon="heroicons:rocket-launch" class="size-3.5" />
                    {{ publishing ? 'Publishing…' : funnel.status === 'published' ? 'Re-publish' : 'Publish' }}
                </Button>
            </div>
        </div>

        <!-- ── Tabs ── -->
        <Tabs v-model="activeTab" default-value="optin" class="space-y-5">
            <TabsList class="h-auto gap-0.5 p-1 bg-muted rounded-xl w-full sm:w-auto">
                <TabsTrigger value="optin" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:cursor-arrow-ripple" class="size-3.5" />
                    Opt-in Editor
                </TabsTrigger>
                <TabsTrigger value="webinar" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:video-camera" class="size-3.5" />
                    Webinar Room
                </TabsTrigger>
                <TabsTrigger value="integrations" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:puzzle-piece" class="size-3.5" />
                    Integrations
                </TabsTrigger>
                <TabsTrigger value="links" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:link" class="size-3.5" />
                    Share Links
                </TabsTrigger>
                <TabsTrigger value="chat" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-3.5" />
                    Chat
                    <span
                        v-if="conversationSummaries.length > 0"
                        class="ml-0.5 flex size-4 items-center justify-center rounded-full bg-primary text-[0.6rem] font-bold text-primary-foreground"
                    >
                        {{ conversationSummaries.length }}
                    </span>
                </TabsTrigger>
            </TabsList>

            <!-- ── Tab: Opt-in Editor ── -->
            <TabsContent value="optin" class="space-y-4">
                <Card class="border shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold">Opt-in Page Editor</CardTitle>
                        <CardDescription class="text-xs">
                            Drag and drop to customise your opt-in page. The lead capture form is locked.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- GrapesJS canvas -->
                        <div ref="editorContainer" class="overflow-hidden rounded-lg border" />

                        <!-- Tip -->
                        <div class="flex items-start gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3.5 py-2.5 text-xs text-muted-foreground">
                            <Icon icon="heroicons:information-circle" class="size-4 shrink-0 text-primary mt-0.5" />
                            <span>Edit your opt-in page directly in the canvas above. The form (name &amp; email fields) is locked to prevent accidental deletion. Click <strong>Save</strong> when done — your design will appear on the public page exactly as shown.</span>
                        </div>

                        <div class="flex justify-end">
                            <Button
                                size="sm"
                                class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                                :disabled="savingPage || pageForm.processing"
                                @click="savePage"
                            >
                                <Icon
                                    v-if="savingPage"
                                    icon="heroicons:arrow-path"
                                    class="size-3.5 animate-spin"
                                />
                                <Icon v-else icon="heroicons:check" class="size-3.5" />
                                {{ savingPage ? 'Saving…' : 'Save opt-in page' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ── Tab: Webinar Room ── -->
            <TabsContent value="webinar" class="space-y-4">
                <div class="grid gap-4 lg:grid-cols-2">

                    <!-- Room details -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-semibold">Room Details</CardTitle>
                            <CardDescription class="text-xs">Configure the webinar room content</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Webinar Title</Label>
                                <Input
                                    v-model="settingsForm.webinar_title"
                                    class="h-9 text-sm"
                                    placeholder="How to grow your business in 90 days"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Description</Label>
                                <Textarea
                                    v-model="settingsForm.webinar_description"
                                    class="h-20 resize-none text-sm"
                                    placeholder="Brief description shown above the video"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Video URL</Label>
                                <div class="relative">
                                    <Icon icon="heroicons:play-circle" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                                    <Input
                                        v-model="settingsForm.video_url"
                                        type="url"
                                        class="pl-9 h-9 text-sm"
                                        placeholder="https://www.youtube.com/embed/…"
                                    />
                                </div>
                                <p class="text-[0.65rem] text-muted-foreground">Paste a YouTube or Vimeo embed URL</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Room settings -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-semibold">Room Settings</CardTitle>
                            <CardDescription class="text-xs">Behaviour and features for attendees</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <!-- Chat mode -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Chat Mode</Label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="mode in ['simulated', 'hybrid', 'realtime']"
                                        :key="mode"
                                        class="rounded-lg border px-3 py-2.5 text-xs font-medium capitalize transition-colors"
                                        :class="settingsForm.chat_mode === mode
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border text-muted-foreground hover:border-primary/30'"
                                        @click="settingsForm.chat_mode = mode"
                                    >
                                        <Icon
                                            :icon="mode === 'simulated' ? 'heroicons:cpu-chip' : mode === 'hybrid' ? 'heroicons:arrows-right-left' : 'heroicons:bolt'"
                                            class="mx-auto mb-1 size-4"
                                        />
                                        {{ mode }}
                                    </button>
                                </div>
                            </div>

                            <!-- Toggles -->
                            <div class="space-y-3 divide-y divide-border/60">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-foreground">Allow Replay</p>
                                        <p class="text-xs text-muted-foreground">Attendees can watch the recording after the event</p>
                                    </div>
                                    <Switch
                                        :checked="settingsForm.allow_replay"
                                        @update:checked="settingsForm.allow_replay = $event"
                                    />
                                </div>
                                <div class="flex items-center justify-between pt-3">
                                    <div>
                                        <p class="text-sm font-medium text-foreground">Double Opt-in</p>
                                        <p class="text-xs text-muted-foreground">Send a confirmation email before registering</p>
                                    </div>
                                    <Switch
                                        :checked="settingsForm.double_opt_in"
                                        @update:checked="settingsForm.double_opt_in = $event"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="flex justify-end">
                    <Button
                        size="sm"
                        class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                        :disabled="savingSettings || settingsForm.processing"
                        @click="saveSettings"
                    >
                        <Icon
                            v-if="savingSettings"
                            icon="heroicons:arrow-path"
                            class="size-3.5 animate-spin"
                        />
                        <Icon v-else icon="heroicons:check" class="size-3.5" />
                        {{ savingSettings ? 'Saving…' : 'Save settings' }}
                    </Button>
                </div>
            </TabsContent>

            <!-- ── Tab: Integrations ── -->
            <TabsContent value="integrations" class="space-y-4">
                <Card class="border shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold">ESP Integrations</CardTitle>
                        <CardDescription class="text-xs">
                            Connect an email service provider — leads will be auto-subscribed when they register.
                            <Link href="/integrations" class="text-primary underline ml-1">Add more accounts →</Link>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="integrationAccounts.length === 0" class="flex flex-col items-center py-10 gap-3 text-muted-foreground">
                            <Icon icon="heroicons:puzzle-piece" class="size-10 opacity-30" />
                            <p class="text-sm">No integration accounts yet.</p>
                            <Button as-child size="sm" variant="outline">
                                <Link href="/integrations">Connect an ESP</Link>
                            </Button>
                        </div>

                        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="account in integrationAccounts"
                                :key="account.id"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition-colors"
                                :class="settingsForm.integration_account_ids.includes(account.id)
                                    ? 'border-primary bg-primary/5'
                                    : 'hover:border-border/80'"
                            >
                                <input
                                    v-model="settingsForm.integration_account_ids"
                                    type="checkbox"
                                    class="sr-only"
                                    :value="account.id"
                                />
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                                    <Icon :icon="providerIcon(account.provider)" class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-foreground truncate">{{ account.name }}</p>
                                    <p class="text-xs text-muted-foreground capitalize">{{ account.provider }}</p>
                                </div>
                                <Icon
                                    v-if="settingsForm.integration_account_ids.includes(account.id)"
                                    icon="heroicons:check-circle"
                                    class="size-5 shrink-0 text-primary"
                                />
                                <Icon
                                    v-else
                                    icon="heroicons:plus-circle"
                                    class="size-5 shrink-0 text-muted-foreground/50"
                                />
                            </label>
                        </div>

                        <div v-if="integrationAccounts.length > 0" class="flex justify-end mt-4">
                            <Button
                                size="sm"
                                class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                                :disabled="savingSettings || settingsForm.processing"
                                @click="saveSettings"
                            >
                                <Icon icon="heroicons:check" class="size-3.5" />
                                Save integrations
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ── Tab: Share Links ── -->
            <TabsContent value="links" class="space-y-4">
                <!-- Status banner -->
                <div
                    v-if="funnel.status !== 'published'"
                    class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"
                >
                    <Icon icon="heroicons:exclamation-triangle" class="size-5 shrink-0 text-amber-600 mt-0.5" />
                    <div class="text-sm">
                        <p class="font-semibold text-amber-800">Funnel is not published yet</p>
                        <p class="text-amber-700 text-xs mt-0.5">These links won't be publicly accessible until you publish the funnel.</p>
                    </div>
                    <Button
                        size="sm"
                        class="shrink-0 ml-auto h-7 text-xs bg-amber-600 text-white hover:bg-amber-700"
                        :disabled="publishing"
                        @click="publish"
                    >
                        Publish now
                    </Button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Opt-in link -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-2">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-primary/10">
                                    <Icon icon="heroicons:cursor-arrow-ripple" class="size-4 text-primary" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-semibold">Opt-in Page</CardTitle>
                                    <CardDescription class="text-xs">Share this to collect registrations</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex rounded-lg border bg-muted/40 overflow-hidden">
                                <p class="flex-1 truncate px-3 py-2 text-xs text-muted-foreground">{{ publicLinks.optin }}</p>
                                <a
                                    :href="publicLinks.optin"
                                    target="_blank"
                                    class="flex items-center border-l px-2 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                    title="Open in new tab"
                                >
                                    <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                </a>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                class="w-full gap-1.5 text-xs h-8"
                                @click="copyLink('optin')"
                            >
                                <Icon
                                    :icon="copiedLink === 'optin' ? 'heroicons:check' : 'heroicons:clipboard-document'"
                                    class="size-3.5"
                                    :class="copiedLink === 'optin' ? 'text-emerald-600' : ''"
                                />
                                {{ copiedLink === 'optin' ? 'Copied!' : 'Copy opt-in link' }}
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Webinar link -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-2">
                                <div class="flex size-8 items-center justify-center rounded-lg" style="background:rgba(255,173,0,0.1)">
                                    <Icon icon="heroicons:video-camera" class="size-4" style="color:#FFAD00" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-semibold">Webinar Room</CardTitle>
                                    <CardDescription class="text-xs">Direct link to the webinar room</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex rounded-lg border bg-muted/40 overflow-hidden">
                                <p class="flex-1 truncate px-3 py-2 text-xs text-muted-foreground">{{ publicLinks.webinar }}</p>
                                <a
                                    :href="publicLinks.webinar"
                                    target="_blank"
                                    class="flex items-center border-l px-2 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                    title="Open in new tab"
                                >
                                    <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                </a>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                class="w-full gap-1.5 text-xs h-8"
                                @click="copyLink('webinar')"
                            >
                                <Icon
                                    :icon="copiedLink === 'webinar' ? 'heroicons:check' : 'heroicons:clipboard-document'"
                                    class="size-3.5"
                                    :class="copiedLink === 'webinar' ? 'text-emerald-600' : ''"
                                />
                                {{ copiedLink === 'webinar' ? 'Copied!' : 'Copy webinar link' }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>

            <!-- ── Tab: Chat Threads ── -->
            <TabsContent value="chat" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">Attendee Conversations</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ conversationSummaries.length }} thread{{ conversationSummaries.length !== 1 ? 's' : '' }} so far</p>
                    </div>
                    <Button as-child size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90">
                        <a :href="`/funnels/${funnel.id}/chat`">
                            <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                            Open Chat Manager
                        </a>
                    </Button>
                </div>

                <div v-if="conversationSummaries.length === 0" class="flex flex-col items-center rounded-xl border border-dashed py-14 gap-3 text-muted-foreground">
                    <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-10 opacity-30" />
                    <p class="text-sm">No conversations yet. Publish your funnel and share the webinar link.</p>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a
                        v-for="thread in conversationSummaries"
                        :key="thread.conversation_key"
                        :href="`/funnels/${funnel.id}/chat`"
                        class="flex items-start gap-3 rounded-xl border p-3.5 hover:border-primary/30 hover:bg-muted/30 transition-colors"
                    >
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <span class="text-xs font-bold text-primary">{{ thread.attendee_name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground">{{ thread.attendee_name }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ thread.attendee_email ?? 'Anonymous' }}</p>
                            <p class="text-xs text-muted-foreground truncate mt-0.5 italic">{{ thread.latest_message ?? 'No messages yet' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[0.6rem] font-medium text-muted-foreground">
                            {{ thread.message_count }}
                        </span>
                    </a>
                </div>
            </TabsContent>

        </Tabs>
    </div>
</template>
