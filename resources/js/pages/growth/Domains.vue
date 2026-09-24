<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DomainRow = {
    id: number;
    domain: string;
    status: string;
    verified_at: string | null;
    campaign_id: number | null;
    campaign?: { id: number; name: string; slug: string; status: string } | null;
    dns: {
        txt_host: string;
        txt_value: string;
        cname_host: string;
        cname_target: string;
    };
};

const props = defineProps<{
    domains: DomainRow[];
    campaigns: Array<{ id: number; name: string; slug: string; status: string }>;
    app_host: string;
}>();

const form = useForm({
    domain: '',
    campaign_id: '' as number | '',
});

function submitDomain() {
    form.transform((data) => ({
        domain: data.domain,
        campaign_id: data.campaign_id || null,
    })).post('/growth/domains', {
        onSuccess: () => form.reset('domain'),
    });
}

function verifyDomain(id: number) {
    router.post(`/growth/domains/${id}/verify`, {}, { preserveScroll: true });
}

function updateCampaign(domain: DomainRow, campaignId: string) {
    router.patch(`/growth/domains/${domain.id}`, {
        campaign_id: campaignId ? Number(campaignId) : null,
    }, { preserveScroll: true });
}

function removeDomain(id: number) {
    if (!confirm('Remove this domain mapping?')) return;
    router.delete(`/growth/domains/${id}`, { preserveScroll: true });
}

function statusClass(status: string): string {
    if (status === 'active') return 'border-blue-200 bg-blue-50 text-blue-700';
    if (status === 'failed') return 'border-rose-200 bg-rose-50 text-rose-700';
    return 'border-amber-200 bg-amber-50 text-amber-700';
}
</script>

<template>
    <Head title="Custom Domains" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight md:text-2xl">Custom domains</h1>
            <p class="mt-0.5 text-sm text-muted-foreground">
                Map a domain to a campaign so squeeze, thank-you, bonus, and quiz pages run on your brand URL.
            </p>
        </div>

        <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold">Add domain</h2>
            <p class="mt-1 text-xs text-muted-foreground">Example: <span class="font-mono">offers.mybrand.com</span></p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="space-y-1.5 sm:col-span-2">
                    <Label>Domain</Label>
                    <Input v-model="form.domain" placeholder="offers.mybrand.com" />
                </div>
                <div class="space-y-1.5">
                    <Label>Campaign</Label>
                    <select v-model="form.campaign_id" class="h-10 w-full rounded-xl border border-border/60 bg-white px-3 text-sm shadow-sm">
                        <option value="">Select campaign…</option>
                        <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <Button variant="brand" class="w-full" :disabled="form.processing || !form.domain.trim()" @click="submitDomain">
                        Add domain
                    </Button>
                </div>
            </div>
        </div>

        <div v-if="!domains.length" class="rounded-xl border border-dashed border-border/60 bg-white p-8 text-center shadow-sm">
            <Icon icon="heroicons:globe-alt" class="mx-auto size-10 text-muted-foreground/40" />
            <p class="mt-3 text-sm font-medium">No custom domains yet</p>
            <p class="mt-1 text-xs text-muted-foreground">Connect a subdomain and map it to a published campaign.</p>
        </div>

        <div v-for="domain in domains" :key="domain.id" class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-mono text-sm font-semibold">{{ domain.domain }}</p>
                        <span class="rounded-md border px-2 py-0.5 text-[0.65rem] font-medium capitalize" :class="statusClass(domain.status)">
                            {{ domain.status }}
                        </span>
                    </div>
                    <p v-if="domain.verified_at" class="mt-1 text-xs text-muted-foreground">
                        Verified {{ new Date(domain.verified_at).toLocaleString() }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="domain.status !== 'active'" variant="brand-outline" size="sm" @click="verifyDomain(domain.id)">
                        Verify DNS
                    </Button>
                    <Button variant="ghost" size="sm" class="text-muted-foreground hover:text-destructive" @click="removeDomain(domain.id)">
                        Remove
                    </Button>
                </div>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <Label>Mapped campaign</Label>
                    <select
                        class="h-10 w-full rounded-xl border border-border/60 bg-white px-3 text-sm shadow-sm"
                        :value="domain.campaign_id ?? ''"
                        @change="updateCampaign(domain, ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="">Not linked</option>
                        <option v-for="c in campaigns" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div v-if="domain.status === 'active' && domain.campaign" class="rounded-lg border border-blue-200/60 bg-blue-50/40 p-3 text-xs">
                    <p class="font-medium text-blue-800">Live URLs</p>
                    <p class="mt-1 font-mono text-blue-700">https://{{ domain.domain }}/</p>
                    <p class="font-mono text-blue-700">https://{{ domain.domain }}/p/thankyou</p>
                </div>
            </div>

            <div class="mt-4 space-y-3 rounded-lg border border-border/60 bg-muted/20 p-3 text-xs">
                <p class="font-semibold text-foreground">DNS setup</p>
                <div>
                    <p class="text-muted-foreground">1. Point your domain to this app (CNAME or A record):</p>
                    <p class="mt-1 font-mono">{{ domain.dns.cname_host }} → {{ domain.dns.cname_target }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground">2. Add TXT record to verify ownership:</p>
                    <p class="mt-1 font-mono break-all">{{ domain.dns.txt_host }}</p>
                    <p class="mt-1 font-mono break-all">{{ domain.dns.txt_value }}</p>
                </div>
                <p class="text-muted-foreground">SSL: terminate HTTPS at your host (Cloudflare, nginx, etc.) — CNAME to <span class="font-mono">{{ app_host }}</span>.</p>
            </div>
        </div>
    </div>
</template>
