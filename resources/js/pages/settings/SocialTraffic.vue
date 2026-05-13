<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type SocialRow = {
    id: number;
    platform: string;
    platform_username: string | null;
    created_at: string;
};

const props = defineProps<{
    socialAccounts: SocialRow[];
    redditConfigured: boolean;
    youtubeConfigured: boolean;
    xConfigured: boolean;
}>();

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
    configured: boolean;
    redirectHref: string;
    connectLabel: string;
};

const platforms = [
    {
        key: 'reddit',
        label: 'Reddit',
        icon: 'simple-icons:reddit',
        iconColor: '#ff6b35',
        get configured() { return props.redditConfigured; },
        redirectHref: '/settings/social-traffic/reddit/redirect',
        connectLabel: 'Connect Reddit account',
    },
    {
        key: 'youtube',
        label: 'YouTube',
        icon: 'simple-icons:youtube',
        iconColor: '#ff0000',
        get configured() { return props.youtubeConfigured; },
        redirectHref: '/settings/social-traffic/youtube/redirect',
        connectLabel: 'Connect YouTube account',
    },
    {
        key: 'twitter',
        label: 'X (Twitter)',
        icon: 'simple-icons:x',
        iconColor: '#e2e8f0',
        get configured() { return props.xConfigured; },
        redirectHref: '/settings/social-traffic/x/redirect',
        connectLabel: 'Connect X account',
    },
] satisfies PlatformInfo[];

function platformIcon(key: string) {
    return platforms.find((p) => p.key === key || p.key === 'twitter' && key === 'twitter') ?? null;
}

function connectedAccount(key: string): SocialRow | undefined {
    return props.socialAccounts.find((a) => a.platform === key);
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
            description="Connect the accounts the AI should use when posting replies."
        />

        <!-- Platform cards -->
        <div class="space-y-3">
            <Card
                v-for="platform in platforms"
                :key="platform.key"
                class="border shadow-sm"
                :class="connectedAccount(platform.key) ? 'border-green-500/40 bg-green-500/5' : ''"
            >
                <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-4">
                    <!-- Platform icon + name -->
                    <div class="flex shrink-0 size-10 items-center justify-center rounded-xl bg-muted">
                        <Icon :icon="platform.icon" class="size-5" :style="{ color: platform.iconColor }" />
                    </div>

                    <!-- Info -->
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
                            <Badge v-else-if="!platform.configured" variant="outline" class="text-[0.6rem] h-5 text-muted-foreground">
                                Not configured
                            </Badge>
                        </div>
                    </div>

                    <!-- Action button -->
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
                            v-else-if="platform.configured"
                            as-child
                            size="sm"
                            class="h-9 gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                        >
                            <Link :href="platform.redirectHref">
                                <Icon :icon="platform.icon" class="size-3.5" />
                                {{ platform.connectLabel }}
                            </Link>
                        </Button>
                        <Button v-else size="sm" variant="outline" class="h-9 text-xs opacity-50 cursor-not-allowed" disabled>
                            Configure in .env first
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- All connected accounts summary -->
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
                    No accounts connected yet. Use the buttons above to connect Reddit, YouTube, or X.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
