<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AdPlatform = { label: string; icon: string };
type Suggestion = { id: string; name: string | null };

const props = defineProps<{
    adPlatforms: Record<string, AdPlatform>;
    savedAdAccountIds: Record<string, string>;
    suggestions: Record<string, Suggestion[]>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Ad accounts', href: '/settings/ad-accounts' },
        ],
    },
});

const formIds = ref<Record<string, string>>({ ...props.savedAdAccountIds });

watch(
    () => props.savedAdAccountIds,
    (ids) => {
        formIds.value = { ...ids };
    },
);

const platformKeys = computed(() => Object.keys(props.adPlatforms));

function hint(platform: string): string {
    return ({
        facebook: 'act_1234567890',
        instagram: 'act_1234567890',
        tiktok: 'Advertiser ID',
        google: '123-456-7890',
        x: '18ce54d4x5t',
        linkedin: 'Sponsored account ID',
        pinterest: 'Ad account ID',
        reddit: 'Ad account ID',
        youtube: 'Google Ads customer ID',
    } as Record<string, string>)[platform] ?? 'Platform ad account ID';
}

function applySuggestion(platform: string, id: string): void {
    formIds.value = { ...formIds.value, [platform]: id };
}
</script>

<template>
    <Head title="Ad accounts" />

    <h1 class="sr-only">Ad account settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Platform ad account IDs"
            description="Save billing account IDs once. They pre-fill when you create paid ad campaigns. Connect social pages under Social posting first."
        />

        <Card class="border shadow-sm">
            <CardHeader class="pb-2">
                <CardTitle class="text-sm font-semibold">Where these IDs come from</CardTitle>
                <CardDescription class="text-xs leading-relaxed">
                    These are <strong>billing accounts</strong> in Meta Ads Manager, TikTok Ads, etc. — not your Zernio connected page.
                    Media spend is charged here. Find them in your platform's ad manager (Meta: <code class="text-[0.65rem]">act_…</code>).
                </CardDescription>
            </CardHeader>
            <CardContent class="pt-0">
                <p class="text-xs text-muted-foreground">
                    Need to connect Facebook/Instagram first?
                    <Link href="/settings/social-traffic" class="font-medium text-primary underline underline-offset-2">Social posting settings</Link>
                </p>
            </CardContent>
        </Card>

        <Form
            action="/settings/ad-accounts"
            method="patch"
            class="space-y-4"
            #default="{ processing }"
        >
            <Card
                v-for="platform in platformKeys"
                :key="platform"
                class="border shadow-sm"
                :class="formIds[platform] ? 'border-green-500/30 bg-green-500/5' : ''"
            >
                <CardContent class="space-y-3 p-4">
                    <div class="flex items-center gap-2">
                        <Icon :icon="adPlatforms[platform]?.icon ?? 'heroicons:share'" class="size-4" />
                        <span class="text-sm font-semibold">{{ adPlatforms[platform]?.label ?? platform }}</span>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs">Ad account ID</Label>
                        <Input
                            v-model="formIds[platform]"
                            :name="`platform_ad_account_ids[${platform}]`"
                            :placeholder="hint(platform)"
                            class="h-9 text-sm font-mono"
                        />
                    </div>

                    <div v-if="(suggestions[platform]?.length ?? 0) > 0" class="space-y-1.5">
                        <p class="text-[0.65rem] font-medium text-muted-foreground">Found from your connected account:</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="item in suggestions[platform]"
                                :key="item.id"
                                type="button"
                                class="rounded-md border border-border bg-background px-2 py-1 text-[0.65rem] hover:border-primary/40 hover:bg-primary/5 transition-colors text-left"
                                @click="applySuggestion(platform, item.id)"
                            >
                                <span class="font-mono">{{ item.id }}</span>
                                <span v-if="item.name" class="text-muted-foreground"> — {{ item.name }}</span>
                            </button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" class="bg-primary text-primary-foreground hover:opacity-90" :disabled="processing">
                    Save ad account IDs
                </Button>
            </div>
        </Form>
    </div>
</template>
