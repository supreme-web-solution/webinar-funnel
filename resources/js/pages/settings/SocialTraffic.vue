<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
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

type ZernioConnectFlash = {
    platform: string;
    message: string;
    type?: 'duplicate_profile' | 'error';
    linkProfileUrl?: string;
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
    zernioConnectPrompt?: ZernioConnectFlash | null;
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
    return flash?.zernioConnect ?? props.zernioConnectPrompt ?? null;
});

const duplicateProfileModalOpen = ref(false);
const linkingProfile = ref(false);

watch(
    zernioConnectAlert,
    (flash) => {
        duplicateProfileModalOpen.value = flash?.type === 'duplicate_profile';
    },
    { immediate: true },
);

function linkExistingZernioProfile() {
    const flash = zernioConnectAlert.value;
    if (!flash?.linkProfileUrl) {
        return;
    }

    linkingProfile.value = true;

    const payload =
        flash.platform && flash.platform !== 'social' ? { platform: flash.platform } : {};

    router.post(flash.linkProfileUrl, payload, {
        preserveScroll: true,
        onFinish: () => {
            linkingProfile.value = false;
        },
    });
}

function cancelDuplicateProfileModal() {
    duplicateProfileModalOpen.value = false;
}

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
        billingNote: '',
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
};

const trafficPlatformKeys = computed(() => props.trafficPlatforms ?? ['reddit', 'youtube', 'x']);
const postingPlatformKeys = computed(() => props.postingPlatforms ?? ['facebook', 'instagram', 'tiktok', 'linkedin']);

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
            description="Traffic AI uses Reddit, YouTube, and X for auto-replies. Facebook, Instagram, and others are used for promotion posts and paid ads."
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
            v-else-if="zernioConnectAlert && zernioConnectAlert.type !== 'duplicate_profile'"
            class="border-amber-500/40 bg-amber-500/5"
        >
            <CardContent class="space-y-2 p-4 text-sm">
                <p class="font-medium text-foreground">Can't connect right now</p>
                <p class="text-muted-foreground">{{ zernioConnectAlert.message }}</p>
            </CardContent>
        </Card>

        <Dialog :open="duplicateProfileModalOpen" @update:open="duplicateProfileModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Link your Zernio profile</DialogTitle>
                    <DialogDescription class="text-left space-y-2 pt-1">
                        <span>{{ zernioConnectAlert?.message }}</span>
                        <span class="block text-muted-foreground">
                            Continuing links this app to the profile already on your Zernio account. Social
                            accounts you connect here are shared across apps that use the same API key.
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" :disabled="linkingProfile" @click="cancelDuplicateProfileModal">
                        Cancel
                    </Button>
                    <Button :disabled="linkingProfile" @click="linkExistingZernioProfile">
                        {{ linkingProfile ? 'Linking…' : 'Continue' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Posting & paid ads (connect Facebook here for ads) -->
        <div class="space-y-3">
            <div>
                <h2 class="text-sm font-semibold">Posting &amp; paid ads</h2>
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
