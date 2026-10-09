<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface AccountRow {
    id: number;
    name: string;
    username: string;
    email: string;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator {
    data: AccountRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

const props = defineProps<{
    accounts: Paginator;
    filters: {
        search: string;
    };
    stats: {
        total: number;
    };
    accountRole: string;
}>();

const page = usePage();
const search = ref(props.filters.search ?? '');
const deletingId = ref<number | null>(null);

let debounce: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/reseller', { search: value || undefined }, { preserveState: true, replace: true });
    }, 350);
});

const credentials = computed(() => {
    const flash = page.props.flash as { resellerCredentials?: { email: string; password: string } } | undefined;

    return flash?.resellerCredentials ?? null;
});

const form = useForm({
    name: '',
    email: '',
});

function submit(): void {
    form.post('/reseller/accounts', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function deleteAccount(account: AccountRow): void {
    if (!confirm(`Delete the account for "${account.name}" (${account.email})? They will lose access immediately.`)) {
        return;
    }

    deletingId.value = account.id;
    router.delete(`/reseller/accounts/${account.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

function fmtDate(dt: string): string {
    return new Date(dt).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function avatarInitials(name: string): string {
    return name.split(' ').map((p) => p[0]).join('').toUpperCase().slice(0, 2);
}
</script>

<template>
    <Head title="Reseller" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Reseller</h1>
                <p class="mt-0.5 text-sm leading-relaxed text-muted-foreground">
                    Create accounts for your customers. Each account gets {{ accountRole }} access and receives its login details by email.
                </p>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-border/60 bg-white px-3 py-2.5 shadow-sm">
                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                    <Icon icon="heroicons:user-group" class="size-4 text-blue-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">Accounts</p>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-xl font-bold leading-none tabular-nums">{{ stats.total.toLocaleString() }}</span>
                        <span class="text-[0.65rem] text-muted-foreground">created</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create account -->
        <div class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm">
            <div class="border-b border-border/60 bg-blue-50/20 px-4 py-3">
                <p class="text-sm font-semibold text-foreground">Create a customer account</p>
                <p class="text-xs text-muted-foreground">A password is generated automatically and emailed to the customer.</p>
            </div>
            <form class="grid gap-3 p-4 md:grid-cols-[1fr_1fr_auto] md:items-end" @submit.prevent="submit">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Name</label>
                    <Input v-model="form.name" type="text" required />
                    <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Email</label>
                    <Input v-model="form.email" type="email" required />
                    <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                </div>
                <Button type="submit" variant="brand" size="sm" class="h-9" :disabled="form.processing">
                    <Icon :icon="form.processing ? 'heroicons:arrow-path' : 'heroicons:user-plus'" class="size-3.5" :class="form.processing ? 'animate-spin' : ''" />
                    Create account
                </Button>
            </form>
            <div v-if="credentials" class="mx-4 mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <p class="font-medium">Share these login details with your customer:</p>
                <p class="mt-1">Email: <span class="font-mono">{{ credentials.email }}</span></p>
                <p>Password: <span class="font-mono">{{ credentials.password }}</span></p>
            </div>
        </div>

        <!-- Accounts table -->
        <div class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-border/60 p-3 md:flex-row md:items-center md:justify-between md:p-4">
                <div>
                    <p class="text-sm font-semibold text-foreground">Your customer accounts</p>
                    <p v-if="accounts.total > 0" class="text-xs text-muted-foreground">
                        {{ accounts.from }}–{{ accounts.to }} of {{ accounts.total.toLocaleString() }}
                    </p>
                </div>
                <div class="relative max-w-md flex-1 md:max-w-xs">
                    <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search name, username, email…" class="pl-9" />
                </div>
            </div>

            <div v-if="accounts.data.length === 0" class="flex flex-col items-center justify-center gap-4 px-6 py-14 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-blue-500/10">
                    <Icon icon="heroicons:user-plus" class="size-7 text-blue-600/60" />
                </div>
                <div>
                    <p class="font-semibold text-foreground">No accounts yet</p>
                    <p class="mt-1 text-sm text-muted-foreground">Accounts you create for your customers will appear here.</p>
                </div>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border/60 bg-blue-50/20">
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Customer</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Email</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Access</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Created</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="account in accounts.data" :key="account.id" class="transition-colors hover:bg-blue-50/20">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-[0.65rem] font-bold text-blue-700">
                                        {{ avatarInitials(account.name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-foreground">{{ account.name }}</p>
                                        <p class="text-xs text-muted-foreground">@{{ account.username }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-foreground">{{ account.email }}</td>
                            <td class="px-4 py-3">
                                <Badge variant="outline" class="border-blue-200 bg-blue-50 text-[0.65rem] text-blue-700">{{ accountRole }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ fmtDate(account.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 text-xs text-muted-foreground hover:text-destructive"
                                        :disabled="deletingId === account.id"
                                        @click="deleteAccount(account)"
                                    >
                                        <Icon
                                            :icon="deletingId === account.id ? 'heroicons:arrow-path' : 'heroicons:trash'"
                                            class="size-3.5"
                                            :class="deletingId === account.id ? 'animate-spin' : ''"
                                        />
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="accounts.last_page > 1" class="flex items-center justify-between border-t border-border/60 px-4 py-3">
                <p class="text-xs text-muted-foreground">Page {{ accounts.current_page }} of {{ accounts.last_page }}</p>
                <div class="flex items-center gap-1">
                    <button
                        v-for="link in accounts.links"
                        :key="link.label"
                        :disabled="!link.url"
                        class="inline-flex h-7 min-w-7 items-center justify-center rounded-lg border px-1.5 text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                        :class="link.active ? 'chip-brand-active' : 'border-border/60 bg-white text-foreground hover:bg-blue-50'"
                        @click="link.url && router.get(link.url, {}, { preserveState: true })"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
