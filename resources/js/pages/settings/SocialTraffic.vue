<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
    trafficPlatforms?: string[];
    postingPlatforms?: string[];
    facebookAdsDiagnostics?: {
        page_name: string | null;
        zernio_page_account_id: string;
        has_metaads_token: boolean;
        zernio_metaads_account_id: string | null;
        billing_ad_accounts: Array<{ id: string; name: string | null; currency: string | null }>;
        list_error: string | null;
    } | null;
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
    purpose: 'traffic' | 'posting';
};

const PLATFORM_CATALOG: Record<string, Omit<PlatformInfo, 'key' | 'purpose'>> = {
    reddit: {
        label: 'Reddit',
        icon: 'simple-icons:reddit',
        iconColor: '#ff6b35',
        redirectHref: '/settings/social-traffic/reddit/redirect',
        connectLabel: 'Connect Reddit',
    },
    youtube: {
        label: 'YouTube',
        icon: 'simple-icons:youtube',
        iconColor: '#ff0000',
        redirectHref: '/settings/social-traffic/youtube/redirect',
        connectLabel: 'Connect YouTube',
    },
    twitter: {
        label: 'X (Twitter)',
        icon: 'simple-icons:x',
        iconColor: '#e2e8f0',
        redirectHref: '/settings/social-traffic/x/redirect',
        connectLabel: 'Connect X',
    },
    facebook: {
        label: 'Facebook',
        icon: 'simple-icons:facebook',
        iconColor: '#1877f2',
        redirectHref: '/settings/social-traffic/facebook/redirect',
        connectLabel: 'Connect Facebook',
        billingNote: 'You log in with your personal Facebook profile, then pick the Page that will run ads (e.g. Vickenconcept). That is correct. Your Meta billing ad account ID (act_…) is entered separately under Settings → Ad accounts.',
    },
    instagram: {
        label: 'Instagram',
        icon: 'simple-icons:instagram',
        iconColor: '#e4405f',
        redirectHref: '/settings/social-traffic/instagram/redirect',
        connectLabel: 'Connect Instagram',
    },
    tiktok: {
        label: 'TikTok',
        icon: 'simple-icons:tiktok',
        iconColor: '#ffffff',
        redirectHref: '/settings/social-traffic/tiktok/redirect',
        connectLabel: 'Connect TikTok',
    },
    linkedin: {
        label: 'LinkedIn',
        icon: 'simple-icons:linkedin',
        iconColor: '#0a66c2',
        redirectHref: '/settings/social-traffic/linkedin/redirect',
        connectLabel: 'Connect LinkedIn',
        billingNote: 'You may need to pick a LinkedIn organization after OAuth.',
    },
    pinterest: {
        label: 'Pinterest',
        icon: 'simple-icons:pinterest',
        iconColor: '#e60023',
        redirectHref: '/settings/social-traffic/pinterest/redirect',
        connectLabel: 'Connect Pinterest',
        billingNote: 'You may need to pick a board after OAuth.',
    },
};

const trafficPlatformKeys = computed(() => props.trafficPlatforms ?? ['reddit', 'youtube', 'x']);
const postingPlatformKeys = computed(() => props.postingPlatforms ?? ['facebook', 'instagram', 'tiktok', 'linkedin', 'pinterest']);

function buildPlatforms(keys: string[], purpose: PlatformInfo['purpose']): PlatformInfo[] {
    return keys.map((key) => {
        const catalogKey = key === 'x' ? 'twitter' : key;
        const base = PLATFORM_CATALOG[catalogKey];
        if (!base) {
            return {
                key,
                label: key,
                icon: 'heroicons:share',
                iconColor: '#94a3b8',
                redirectHref: `/settings/social-traffic/${key}/redirect`,
                connectLabel: `Connect ${key}`,
                purpose,
            };
        }
        return { key, ...base, purpose };
    });
}

const trafficPlatforms = computed(() => buildPlatforms(trafficPlatformKeys.value, 'traffic'));
const postingPlatforms = computed(() => buildPlatforms(postingPlatformKeys.value, 'posting'));

function platformIcon(key: string) {
    const catalogKey = key === 'twitter' || key === 'x' ? 'twitter' : key;
    return PLATFORM_CATALOG[catalogKey] ?? null;
}

function connectedAccount(key: string): SocialRow | undefined {
    return props.socialAccounts.find((a) => {
        if (a.platform === key) {
            return true;
        }
        if (key === 'twitter' && (a.platform === 'twitter' || a.platform === 'x')) {
            return true;
        }
        if (key === 'x' && (a.platform === 'twitter' || a.platform === 'x')) {
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
    if (!confirm(`Disconnect ${platform}? Your funnel settings for this account may need to be re-mapped.`)) {
        return;
    }
    router.delete(`/settings/social-traffic/${id}`, { preserveScroll: true });
}

function displayPlatformName(platform: string): string {
    if (platform === 'twitter') return 'X (Twitter)';
    return platform.charAt(0).toUpperCase() + platform.slice(1);
}
</script>

<template>
    <Head title="Social posting" />

    <h1 class="sr-only">Social posting</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Connected social accounts"
            description="Connect platforms through Zernio. Traffic AI uses Reddit, YouTube, and X for auto-replies. Facebook, Instagram, and others are used for promotion posts and paid ads."
        />

        <Card v-if="appUrlMismatch" class="border-amber-500/40 bg-amber-500/5">
            <CardContent class="space-y-3 p-4 text-sm text-muted-foreground">
                <p class="font-medium text-foreground">Open the app using APP_URL (required for OAuth)</p>
                <p>
                    You are on <code class="text-xs">{{ requestOrigin }}</code> but
                    <code class="text-xs">APP_URL</code> is <code class="text-xs">{{ appUrl }}</code>.
                    Connecting social accounts only works when you browse and log in on
                    <strong>the same host</strong> as <code class="text-xs">APP_URL</code>.
                </p>
                <Button v-if="appUrl" as-child size="sm" variant="outline">
                    <a :href="connectHref('/settings/social-traffic')">Open {{ appUrl }}/settings/social-traffic</a>
                </Button>
            </CardContent>
        </Card>

        <Card v-if="!zernioConfigured" class="border-amber-500/40 bg-amber-500/5">
            <CardContent class="p-4 text-sm text-muted-foreground">
                Add <code class="text-xs">ZERNIO_API_KEY</code> to your environment to enable account connections.
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
                    To test auto-replies now, connect <strong>Reddit</strong> or <strong>YouTube</strong> below.
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

        <!-- Posting & paid ads (connect Facebook here for ads) -->
        <div class="space-y-3">
            <div>
                <h2 class="text-sm font-semibold">Posting &amp; paid ads</h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Required to run Facebook/Instagram ads and publish promotion posts.
                    <strong>Connect Facebook here</strong> if your ad campaign shows “No Facebook account connected”.
                    The Zernio page picker (Vickenconcept vs VixBlock) only chooses which <strong>Page</strong> publishes ads — not the billing ad account.
                </p>
            </div>
            <div
                v-if="facebookAdsDiagnostics && connectedAccount('facebook')"
                class="rounded-lg border border-blue-500/25 bg-blue-500/5 px-4 py-3 text-xs space-y-2"
            >
                <p class="font-semibold text-blue-800 dark:text-blue-300">Facebook ads connection check</p>
                <ul class="space-y-1 text-muted-foreground list-disc pl-4">
                    <li>Page connected: <strong class="text-foreground">{{ facebookAdsDiagnostics.page_name ?? 'Facebook page' }}</strong> — this is expected.</li>
                    <li v-if="facebookAdsDiagnostics.billing_ad_accounts.length">
                        Billing ad accounts visible to this connection:
                        <span v-for="(acct, idx) in facebookAdsDiagnostics.billing_ad_accounts" :key="acct.id">
                            <strong class="text-foreground">{{ acct.name ?? acct.id }}</strong>
                            <span v-if="acct.currency"> ({{ acct.currency }})</span>{{ idx < facebookAdsDiagnostics.billing_ad_accounts.length - 1 ? ', ' : '' }}
                        </span>
                    </li>
                    <li v-else-if="facebookAdsDiagnostics.list_error" class="text-amber-700 dark:text-amber-400">
                        Could not list ad accounts: {{ facebookAdsDiagnostics.list_error }}
                    </li>
                    <li v-else class="text-amber-700 dark:text-amber-400">
                        No billing ad accounts returned — the personal profile you used to connect may not have access to your act_… ad account in Business Manager.
                    </li>
                </ul>
                <p class="text-muted-foreground leading-relaxed">
                    If launch fails with Meta “authenticate in Ads Manager”, that is Meta blocking <strong>API</strong> writes (via Zernio) — not a wrong page pick. Complete verification in Ads Manager as the same personal profile that connected here, then retry.
                </p>
            </div>
            <Card
                v-for="platform in postingPlatforms"
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

        <!-- Traffic auto-replies -->
        <div class="space-y-3">
            <div>
                <h2 class="text-sm font-semibold">Traffic auto-replies</h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Apify finds mentions globally. Connect these accounts so Traffic AI can post replies.
                </p>
            </div>
            <Card
                v-for="platform in trafficPlatforms"
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
                <CardDescription class="text-xs">
                    Used for Traffic AI replies, promotion publishing, and paid ad campaigns.
                </CardDescription>
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
                            <span class="font-medium">{{ displayPlatformName(a.platform) }}</span>
                            <span v-if="a.platform_username" class="text-muted-foreground truncate">{{ a.platform_username }}</span>
                        </div>
                        <Button variant="ghost" size="sm" class="h-8 text-xs text-muted-foreground hover:text-destructive" @click="disconnect(a.id, a.platform)">
                            Remove
                        </Button>
                    </li>
                </ul>
                <p v-else class="py-4 text-center text-xs text-muted-foreground">
                    No accounts connected yet. Connect Facebook above to run paid ads.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
