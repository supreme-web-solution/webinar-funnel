<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type ZernioConnectFlash = {
    platform: string;
    code: string;
    message: string;
    dashboard_url: string;
    documentation_url?: string;
};

type SocialRow = {
    id: number;
    platform: string;
    platform_username: string | null;
    zernio_account_id: string | null;
    created_at: string;
};

const props = defineProps<{
    socialAccounts: SocialRow[];
    zernioConfigured: boolean;
    oauthCallbackUrl?: string;
    appUrlMismatch?: boolean;
    appUrl?: string;
    requestOrigin?: string;
}>();

const page = usePage();

const zernioConnectAlert = computed(() => {
    const flash = page.props.flash as { zernioConnect?: ZernioConnectFlash } | undefined;
    return flash?.zernioConnect ?? null;
});

function platformErrorKey(platformKey: string): string {
    return platformKey === 'twitter' ? 'x' : platformKey;
}

function platformConnectError(platformKey: string): string | undefined {
    const errors = page.props.errors as Record<string, string> | undefined;
    if (!errors) {
        return undefined;
    }
    const key = platformErrorKey(platformKey);
    return errors[key] ?? errors[platformKey];
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Social posting', href: '/settings/social-traffic' },
        ],
    },
});

type PlatformInfo = {
    key: string;
    label: string;
    icon: string;
    iconColor: string;
    redirectHref: string;
    connectLabel: string;
    billingNote?: string;
};

const platforms = [
    {
        key: 'reddit',
        label: 'Reddit',
        icon: 'simple-icons:reddit',
        iconColor: '#ff6b35',
        redirectHref: '/settings/social-traffic/reddit/redirect',
        connectLabel: 'Connect Reddit',
    },
    {
        key: 'youtube',
        label: 'YouTube',
        icon: 'simple-icons:youtube',
        iconColor: '#ff0000',
        redirectHref: '/settings/social-traffic/youtube/redirect',
        connectLabel: 'Connect YouTube',
    },
    {
        key: 'twitter',
        label: 'X (Twitter)',
        icon: 'simple-icons:x',
        iconColor: '#e2e8f0',
        redirectHref: '/settings/social-traffic/x/redirect',
        connectLabel: 'Connect X',
        billingNote: '',
    },
] satisfies PlatformInfo[];

function platformIcon(key: string) {
    return platforms.find((p) => p.key === key || p.key === 'twitter' && key === 'twitter') ?? null;
}

function connectedAccount(key: string): SocialRow | undefined {
    return props.socialAccounts.find((a) => {
        if (a.platform === key) {
            return true;
        }
        if (key === 'twitter' && (a.platform === 'twitter' || a.platform === 'x')) {
            return true;
        }
        return false;
    });
}

function connectHref(path: string): string {
    const base = (props.appUrl ?? '').replace(/\/$/, '');
    return base !== '' ? `${base}${path}` : path;
}

function disconnect(id: number, platform: string): void {
    if (!confirm(`Disconnect ${platform}? Your funnel AI-reply settings for this account will also need to be re-mapped.`)) {
        return;
    }
    router.delete(`/settings/social-traffic/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Social posting" />

    <h1 class="sr-only">Social posting</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Social posting for traffic auto-replies"
            description="Apify discovers mentions globally (Reddit, YouTube, X, news). Connect accounts here via Zernio so the AI can post replies."
        />

        <Card v-if="appUrlMismatch" class="border-amber-500/40 bg-amber-500/5">
            <CardContent class="space-y-3 p-4 text-sm text-muted-foreground">
                <p class="font-medium text-foreground">Open the app using APP_URL (required for OAuth)</p>
                <p>
                    You are on <code class="text-xs">{{ requestOrigin }}</code> but
                    <code class="text-xs">APP_URL</code> is <code class="text-xs">{{ appUrl }}</code>.
                    Connecting social accounts only works when you browse and log in on
                    <strong>the same host</strong> as <code class="text-xs">APP_URL</code>
                    (otherwise you are sent to login after OAuth).
                </p>
                <Button v-if="appUrl" as-child size="sm" variant="outline">
                    <a :href="connectHref('/settings/social-traffic')">Open {{ appUrl }}/settings/social-traffic</a>
                </Button>
            </CardContent>
        </Card>

        <Card v-if="!zernioConfigured" class="border-amber-500/40 bg-amber-500/5">
            <CardContent class="p-4 text-sm text-muted-foreground">
                Add <code class="text-xs">ZERNIO_API_KEY</code> to your environment to enable account connections and replies.
            </CardContent>
        </Card>

        <Card
            v-else-if="zernioConnectAlert?.code === 'PAYMENT_REQUIRED'"
            class="border-amber-500/40 bg-amber-500/5"
        >
            <CardContent class="space-y-3 p-4 text-sm">
                <p class="font-medium text-foreground">X (Twitter) needs a payment method on Zernio</p>
                <p class="text-muted-foreground">{{ zernioConnectAlert.message }}</p>
                <p class="text-muted-foreground">
                    To test auto-replies now, connect <strong>Reddit</strong> or <strong>YouTube</strong> below — they do not require this step.
                    Mention discovery for X still works via Apify without a connected account.
                </p>
                <div class="flex flex-wrap gap-2">
                    <Button as-child size="sm" variant="default">
                        <a :href="zernioConnectAlert.dashboard_url" target="_blank" rel="noopener noreferrer">
                            Add payment method in Zernio
                        </a>
                    </Button>
                    <Button
                        v-if="zernioConnectAlert.documentation_url"
                        as-child
                        size="sm"
                        variant="outline"
                    >
                        <a :href="zernioConnectAlert.documentation_url" target="_blank" rel="noopener noreferrer">
                            Why is this required?
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>

        <div class="space-y-3">
            <Card
                v-for="platform in platforms"
                :key="platform.key"
                class="border shadow-sm"
                :class="connectedAccount(platform.key) ? 'border-green-500/40 bg-green-500/5' : ''"
            >
                <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-4">
                    <div class="flex shrink-0 size-10 items-center justify-center rounded-xl bg-muted">
                        <Icon :icon="platform.icon" class="size-5" :style="{ color: platform.iconColor }" />
                    </div>

                    <div class="flex-1 min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold">{{ platform.label }}</span>
                            <Badge
                                v-if="connectedAccount(platform.key)"
                                variant="outline"
                                class="text-[0.6rem] h-5 border-green-500/50 text-green-600 dark:text-green-400"
                            >
                                Connected<span v-if="connectedAccount(platform.key)?.platform_username"> as {{ connectedAccount(platform.key)?.platform_username }}</span>
                            </Badge>
                        </div>
                        <p v-if="platform.billingNote && !connectedAccount(platform.key)" class="text-xs text-muted-foreground">
                            {{ platform.billingNote }}
                        </p>
                        <p v-if="platformConnectError(platform.key)" class="text-xs text-destructive">
                            {{ platformConnectError(platform.key) }}
                        </p>
                    </div>

                    <div class="shrink-0">
                        <Button
                            v-if="connectedAccount(platform.key)"
                            variant="outline"
                            size="sm"
                            class="h-9 text-xs gap-1.5 border-destructive/40 text-destructive hover:bg-destructive/10"
                            @click="disconnect(connectedAccount(platform.key)!.id, platform.label)"
                        >
                            <Icon icon="heroicons:link-slash-20-solid" class="size-3.5" />
                            Disconnect
                        </Button>
                        <Button
                            v-else-if="zernioConfigured"
                            as-child
                            size="sm"
                            class="h-9 gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                        >
                            <a :href="connectHref(platform.redirectHref)">
                                <Icon :icon="platform.icon" class="size-3.5" />
                                {{ platform.connectLabel }}
                            </a>
                        </Button>
                        <Button v-else size="sm" variant="outline" class="h-9 text-xs opacity-50 cursor-not-allowed" disabled>
                            Configure ZERNIO_API_KEY first
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card class="border shadow-sm">
            <CardHeader class="pb-2">
                <CardTitle class="text-sm font-semibold">All connected accounts</CardTitle>
                <CardDescription class="text-xs">These are available in the platform dropdowns on each funnel's Traffic Settings tab.</CardDescription>
            </CardHeader>
            <CardContent>
                <ul v-if="socialAccounts.length" class="divide-y divide-border">
                    <li
                        v-for="a in socialAccounts"
                        :key="a.id"
                        class="flex items-center justify-between gap-3 py-2.5 text-sm"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <Icon
                                :icon="platformIcon(a.platform)?.icon ?? 'heroicons:user-circle'"
                                class="size-4 shrink-0"
                                :style="{ color: platformIcon(a.platform)?.iconColor ?? '#94a3b8' }"
                            />
                            <span class="font-medium capitalize">{{ a.platform === 'twitter' ? 'X (Twitter)' : a.platform }}</span>
                            <span v-if="a.platform_username" class="text-muted-foreground truncate">{{ a.platform_username }}</span>
                        </div>
                        <Button variant="ghost" size="sm" class="h-8 text-xs text-muted-foreground hover:text-destructive" @click="disconnect(a.id, a.platform)">
                            Remove
                        </Button>
                    </li>
                </ul>
                <p v-else class="py-4 text-center text-xs text-muted-foreground">
                    No accounts connected yet. Connect platforms above through Zernio.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
