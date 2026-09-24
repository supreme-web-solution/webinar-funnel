<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import PasswordInput from '@/components/PasswordInput.vue';

interface UserRow {
    id: number;
    uuid: string;
    name: string;
    username: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    role?: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator {
    data: UserRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

const props = defineProps<{
    users: Paginator;
    filters: {
        search: string;
    };
    stats: {
        total: number;
        verified: number;
    };
    adminEmails: string[];
    rolesEnabled?: boolean;
    assignableRoles?: string[];
    defaultRole?: string;
}>();

const search = ref(props.filters.search ?? '');
const showCreate = ref(false);
const editingUser = ref<UserRow | null>(null);
const deletingId = ref<number | null>(null);

let debounce: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/users', { search: value || undefined }, { preserveState: true, replace: true });
    }, 350);
});

const statCards = computed(() => [
    { label: 'Total', value: props.stats.total, sub: 'users', icon: 'heroicons:user-group' },
    { label: 'Verified', value: props.stats.verified, sub: 'emails', icon: 'heroicons:check-badge' },
    { label: 'Pending', value: props.stats.total - props.stats.verified, sub: 'unverified', icon: 'heroicons:clock' },
]);

const createForm = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: props.defaultRole ?? 'FE',
});

const editForm = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: props.defaultRole ?? 'FE',
});

function fmtDate(dt: string): string {
    return new Date(dt).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function isProtectedAdmin(user: UserRow): boolean {
    return props.adminEmails.includes((user.email ?? '').toLowerCase());
}

function roleLabel(role: string | null | undefined): string {
    if (!role) return 'FE (default)';
    return role;
}

function avatarInitials(name: string): string {
    return name.split(' ').map((p) => p[0]).join('').toUpperCase().slice(0, 2);
}

function openCreate(): void {
    showCreate.value = true;
    createForm.reset();
    createForm.role = props.defaultRole ?? 'FE';
    createForm.clearErrors();
}

function closeCreate(): void {
    showCreate.value = false;
    createForm.reset();
    createForm.clearErrors();
}

function submitCreate(): void {
    createForm.post('/users', {
        preserveScroll: true,
        onSuccess: () => closeCreate(),
    });
}

function openEdit(user: UserRow): void {
    editingUser.value = user;
    editForm.name = user.name;
    editForm.username = user.username;
    editForm.email = user.email;
    editForm.password = '';
    editForm.password_confirmation = '';
    editForm.role = user.role ?? props.defaultRole ?? 'FE';
    editForm.clearErrors();
}

function closeEdit(): void {
    editingUser.value = null;
    editForm.reset();
    editForm.clearErrors();
}

function submitEdit(): void {
    if (!editingUser.value) return;

    editForm.patch(`/users/${editingUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
}

function deleteUser(user: UserRow): void {
    if (isProtectedAdmin(user)) {
        alert('This admin user is protected by ADMIN_EMAILS and cannot be deleted.');
        return;
    }

    if (!confirm(`Delete user "${user.name}" (${user.email})? This cannot be undone.`)) {
        return;
    }

    deletingId.value = user.id;
    router.delete(`/users/${user.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}
</script>

<template>
    <Head title="User Management" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">User management</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Manage workspace users — create, edit roles, and remove accounts.
                </p>
            </div>
            <Button variant="brand" size="sm" class="shrink-0 self-start" @click="openCreate">
                <Icon icon="heroicons:plus" class="size-3.5" />
                Create user
            </Button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-2 md:grid-cols-3 md:gap-3">
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
                        <span class="text-xl font-bold leading-none tabular-nums">{{ stat.value.toLocaleString() }}</span>
                        <span class="text-[0.65rem] text-muted-foreground">{{ stat.sub }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-border/60 p-3 md:flex-row md:items-center md:justify-between md:p-4">
                <div>
                    <p class="text-sm font-semibold text-foreground">Users</p>
                    <p v-if="users.total > 0" class="text-xs text-muted-foreground">
                        {{ users.from }}–{{ users.to }} of {{ users.total.toLocaleString() }}
                    </p>
                </div>
                <div class="relative max-w-md flex-1 md:max-w-xs">
                    <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search name, username, email…" class="pl-9" />
                </div>
            </div>

            <div v-if="users.data.length === 0" class="flex flex-col items-center justify-center gap-4 px-6 py-14 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-blue-500/10">
                    <Icon icon="heroicons:user-group" class="size-7 text-blue-600/60" />
                </div>
                <div>
                    <p class="font-semibold text-foreground">No users found</p>
                    <p class="mt-1 text-sm text-muted-foreground">Try another search or create a new user.</p>
                </div>
                <Button variant="brand" size="sm" @click="openCreate">Create user</Button>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border/60 bg-blue-50/20">
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">User</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Username</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Email</th>
                            <th v-if="rolesEnabled" class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Role</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Status</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Joined</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-muted-foreground">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="user in users.data" :key="user.id" class="transition-colors hover:bg-blue-50/20">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-[0.65rem] font-bold text-blue-700">
                                        {{ avatarInitials(user.name) }}
                                    </div>
                                    <span class="font-medium text-foreground">{{ user.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">@{{ user.username }}</td>
                            <td class="px-4 py-3 text-foreground">{{ user.email }}</td>
                            <td v-if="rolesEnabled" class="px-4 py-3">
                                <Badge variant="outline" class="border-blue-200 bg-blue-50 text-[0.65rem] text-blue-700">
                                    {{ roleLabel(user.role) }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    variant="outline"
                                    class="text-[0.65rem]"
                                    :class="user.email_verified_at ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                >
                                    {{ user.email_verified_at ? 'Verified' : 'Pending' }}
                                </Badge>
                                <Badge
                                    v-if="isProtectedAdmin(user)"
                                    variant="outline"
                                    class="ml-1 border-blue-200 bg-blue-50 text-[0.65rem] text-blue-700"
                                >
                                    Admin
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ fmtDate(user.created_at) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button variant="brand-outline" size="sm" class="h-7 text-xs" @click="openEdit(user)">
                                        <Icon icon="heroicons:pencil-square" class="size-3.5" />
                                        Edit
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 text-xs text-muted-foreground hover:text-destructive"
                                        :disabled="deletingId === user.id || isProtectedAdmin(user)"
                                        @click="deleteUser(user)"
                                    >
                                        <Icon
                                            :icon="deletingId === user.id ? 'heroicons:arrow-path' : 'heroicons:trash'"
                                            class="size-3.5"
                                            :class="deletingId === user.id ? 'animate-spin' : ''"
                                        />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="users.last_page > 1" class="flex items-center justify-between border-t border-border/60 px-4 py-3">
                <p class="text-xs text-muted-foreground">Page {{ users.current_page }} of {{ users.last_page }}</p>
                <div class="flex items-center gap-1">
                    <button
                        v-for="link in users.links"
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

        <!-- Create dialog -->
        <Dialog :open="showCreate" @update:open="(v) => { if (!v) closeCreate(); else showCreate = true; }">
            <DialogContent class="gap-0 overflow-hidden rounded-xl border border-border/60 p-0 shadow-xl sm:max-w-lg">
                <div class="bg-linear-to-br from-blue-50 via-blue-50/60 to-white px-6 pt-6 pb-3">
                    <DialogHeader class="text-left">
                        <DialogTitle>Create user</DialogTitle>
                        <DialogDescription class="text-xs">Add a new workspace user with email and password.</DialogDescription>
                    </DialogHeader>
                </div>
                <form class="space-y-3 bg-white px-6 py-4" @submit.prevent="submitCreate">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Name</label>
                        <Input v-model="createForm.name" type="text" required />
                        <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Username</label>
                        <Input v-model="createForm.username" type="text" required />
                        <p v-if="createForm.errors.username" class="text-xs text-destructive">{{ createForm.errors.username }}</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Email</label>
                        <Input v-model="createForm.email" type="email" required />
                        <p v-if="createForm.errors.email" class="text-xs text-destructive">{{ createForm.errors.email }}</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Password</label>
                        <PasswordInput v-model="createForm.password" required />
                        <p v-if="createForm.errors.password" class="text-xs text-destructive">{{ createForm.errors.password }}</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Confirm password</label>
                        <PasswordInput v-model="createForm.password_confirmation" required />
                    </div>
                    <div v-if="rolesEnabled && (assignableRoles?.length ?? 0) > 0" class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Role</label>
                        <select
                            v-model="createForm.role"
                            class="h-9 w-full rounded-xl border border-border/60 bg-white px-3 text-sm shadow-sm"
                        >
                            <option v-for="role in assignableRoles" :key="role" :value="role">{{ role }}</option>
                        </select>
                        <p v-if="createForm.errors.role" class="text-xs text-destructive">{{ createForm.errors.role }}</p>
                    </div>
                    <DialogFooter class="border-t border-border/60 bg-muted/10 px-0 pt-4 sm:justify-end">
                        <Button type="button" variant="outline" size="sm" @click="closeCreate">Cancel</Button>
                        <Button type="submit" variant="brand" size="sm" :disabled="createForm.processing">
                            <Icon v-if="createForm.processing" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit dialog -->
        <Dialog :open="!!editingUser" @update:open="(v) => { if (!v) closeEdit(); }">
            <DialogContent class="gap-0 overflow-hidden rounded-xl border border-border/60 p-0 shadow-xl sm:max-w-lg">
                <div class="bg-linear-to-br from-blue-50 via-blue-50/60 to-white px-6 pt-6 pb-3">
                    <DialogHeader class="text-left">
                        <DialogTitle>Edit user</DialogTitle>
                        <DialogDescription class="text-xs">{{ editingUser?.email }}</DialogDescription>
                    </DialogHeader>
                </div>
                <form class="space-y-3 bg-white px-6 py-4" @submit.prevent="submitEdit">
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Name</label>
                        <Input v-model="editForm.name" type="text" required />
                        <p v-if="editForm.errors.name" class="text-xs text-destructive">{{ editForm.errors.name }}</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Username</label>
                        <Input v-model="editForm.username" type="text" required />
                        <p v-if="editForm.errors.username" class="text-xs text-destructive">{{ editForm.errors.username }}</p>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Email</label>
                        <Input v-model="editForm.email" type="email" required />
                        <p v-if="editForm.errors.email" class="text-xs text-destructive">{{ editForm.errors.email }}</p>
                    </div>
                    <div class="rounded-xl border border-border/60 bg-blue-50/20 p-3 space-y-3">
                        <p class="text-xs font-medium text-foreground">Change password</p>
                        <p class="text-xs text-muted-foreground">Leave blank to keep the current password.</p>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">New password</label>
                            <PasswordInput v-model="editForm.password" autocomplete="new-password" />
                            <p v-if="editForm.errors.password" class="text-xs text-destructive">{{ editForm.errors.password }}</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Confirm new password</label>
                            <PasswordInput v-model="editForm.password_confirmation" autocomplete="new-password" />
                        </div>
                    </div>
                    <div v-if="rolesEnabled && (assignableRoles?.length ?? 0) > 0" class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Role</label>
                        <select
                            v-model="editForm.role"
                            class="h-9 w-full rounded-xl border border-border/60 bg-white px-3 text-sm shadow-sm"
                        >
                            <option v-for="role in assignableRoles" :key="role" :value="role">{{ role }}</option>
                        </select>
                        <p v-if="editForm.errors.role" class="text-xs text-destructive">{{ editForm.errors.role }}</p>
                    </div>
                    <DialogFooter class="border-t border-border/60 bg-muted/10 px-0 pt-4 sm:justify-end">
                        <Button type="button" variant="outline" size="sm" @click="closeEdit">Cancel</Button>
                        <Button type="submit" variant="brand" size="sm" :disabled="editForm.processing">
                            <Icon v-if="editForm.processing" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                            Update
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
