<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

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
    return new Date(dt).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function isProtectedAdmin(user: UserRow): boolean {
    return props.adminEmails.includes((user.email ?? '').toLowerCase());
}

function roleLabel(role: string | null | undefined): string {
    if (!role) return 'FE (default)';
    return role;
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

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-foreground">User Management</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Manage all users in your workspace (create, edit, and delete).
                </p>
            </div>

            <Button size="sm" class="gap-1.5 self-start bg-primary text-primary-foreground hover:opacity-90" @click="openCreate">
                <Icon icon="heroicons:plus" class="size-4" />
                Create User
            </Button>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <Card class="border shadow-sm">
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">Total Users</p>
                    <p class="mt-1 text-2xl font-bold text-foreground">{{ stats.total.toLocaleString() }}</p>
                </CardContent>
            </Card>
            <Card class="border shadow-sm">
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">Verified Emails</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-500">{{ stats.verified.toLocaleString() }}</p>
                </CardContent>
            </Card>
        </div>

        <Card class="border shadow-sm">
            <CardHeader class="px-4 pb-3 pt-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <CardTitle class="text-sm font-semibold">
                        Users
                        <span v-if="users.total > 0" class="ml-1.5 text-xs font-normal text-muted-foreground">
                            {{ users.from }}–{{ users.to }} of {{ users.total.toLocaleString() }}
                        </span>
                    </CardTitle>

                    <div class="relative">
                        <Icon icon="heroicons:magnifying-glass" class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="search"
                            type="text"
                            placeholder="Search name, username, email…"
                            class="h-8 w-56 pl-8 text-xs sm:w-72"
                        />
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div v-if="users.data.length === 0" class="flex flex-col items-center justify-center gap-3 py-14 text-center">
                    <div class="flex size-14 items-center justify-center rounded-full bg-primary/10">
                        <Icon icon="heroicons:user-group" class="size-7 text-primary" />
                    </div>
                    <div>
                        <p class="font-semibold text-foreground">No users found</p>
                        <p class="mt-1 text-sm text-muted-foreground">Try another search or create a new user.</p>
                    </div>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/30">
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Name</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Username</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Email</th>
                                <th v-if="rolesEnabled" class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Role</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Status</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Joined</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-muted-foreground">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="user in users.data" :key="user.id" class="transition-colors hover:bg-muted/20">
                                <td class="px-4 py-3 font-medium text-foreground">{{ user.name }}</td>
                                <td class="px-4 py-3 text-muted-foreground">@{{ user.username }}</td>
                                <td class="px-4 py-3 text-foreground">{{ user.email }}</td>
                                <td v-if="rolesEnabled" class="px-4 py-3">
                                    <Badge class="border border-violet-200 bg-violet-50 px-1.5 py-0 text-[0.65rem] text-violet-700">
                                        {{ roleLabel(user.role) }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        class="border px-1.5 py-0 text-[0.65rem]"
                                        :class="user.email_verified_at
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : 'border-amber-200 bg-amber-50 text-amber-700'"
                                    >
                                        {{ user.email_verified_at ? 'Verified' : 'Pending' }}
                                    </Badge>
                                    <Badge
                                        v-if="isProtectedAdmin(user)"
                                        class="ml-1 border border-blue-200 bg-blue-50 px-1.5 py-0 text-[0.65rem] text-blue-700"
                                    >
                                        Admin
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">{{ fmtDate(user.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" size="sm" class="h-7 gap-1 text-xs" @click="openEdit(user)">
                                            <Icon icon="heroicons:pencil-square" class="size-3.5" />
                                            Edit
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-7 gap-1 border-destructive/30 text-xs text-destructive hover:bg-destructive/5 hover:text-destructive"
                                            :disabled="deletingId === user.id || isProtectedAdmin(user)"
                                            @click="deleteUser(user)"
                                        >
                                            <Icon
                                                :icon="deletingId === user.id ? 'heroicons:arrow-path' : 'heroicons:trash'"
                                                class="size-3.5"
                                                :class="deletingId === user.id ? 'animate-spin' : ''"
                                            />
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="users.last_page > 1" class="flex items-center justify-between border-t px-4 py-3">
                    <p class="text-xs text-muted-foreground">
                        Page {{ users.current_page }} of {{ users.last_page }}
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            v-for="link in users.links"
                            :key="link.label"
                            :disabled="!link.url"
                            class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-1.5 text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                            :class="link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background text-foreground hover:bg-muted'"
                            @click="link.url && router.get(link.url, {}, { preserveState: true })"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <Card class="w-full max-w-lg border shadow-xl">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base font-semibold">Create User</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-3" @submit.prevent="submitCreate">
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
                            <Input v-model="createForm.password" type="password" required />
                            <p v-if="createForm.errors.password" class="text-xs text-destructive">{{ createForm.errors.password }}</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Confirm Password</label>
                            <Input v-model="createForm.password_confirmation" type="password" required />
                        </div>
                        <div v-if="rolesEnabled && (assignableRoles?.length ?? 0) > 0" class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Role</label>
                            <select
                                v-model="createForm.role"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option v-for="role in assignableRoles" :key="role" :value="role">
                                    {{ role }}
                                </option>
                            </select>
                            <p v-if="createForm.errors.role" class="text-xs text-destructive">{{ createForm.errors.role }}</p>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" size="sm" @click="closeCreate">Cancel</Button>
                            <Button type="submit" size="sm" :disabled="createForm.processing">
                                <Icon v-if="createForm.processing" icon="heroicons:arrow-path" class="mr-1 size-3.5 animate-spin" />
                                Create
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <div v-if="editingUser" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <Card class="w-full max-w-lg border shadow-xl">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base font-semibold">Edit User</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-3" @submit.prevent="submitEdit">
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
                        <div class="rounded-lg border border-border/80 bg-muted/20 p-3 space-y-3">
                            <p class="text-xs font-medium text-foreground">Change password</p>
                            <p class="text-xs text-muted-foreground">Leave blank to keep the current password.</p>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-muted-foreground">New password</label>
                                <Input v-model="editForm.password" type="password" autocomplete="new-password" />
                                <p v-if="editForm.errors.password" class="text-xs text-destructive">{{ editForm.errors.password }}</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-muted-foreground">Confirm new password</label>
                                <Input v-model="editForm.password_confirmation" type="password" autocomplete="new-password" />
                            </div>
                        </div>
                        <div v-if="rolesEnabled && (assignableRoles?.length ?? 0) > 0" class="space-y-1">
                            <label class="text-xs font-medium text-muted-foreground">Role</label>
                            <select
                                v-model="editForm.role"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option v-for="role in assignableRoles" :key="role" :value="role">
                                    {{ role }}
                                </option>
                            </select>
                            <p v-if="editForm.errors.role" class="text-xs text-destructive">{{ editForm.errors.role }}</p>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" size="sm" @click="closeEdit">Cancel</Button>
                            <Button type="submit" size="sm" :disabled="editForm.processing">
                                <Icon v-if="editForm.processing" icon="heroicons:arrow-path" class="mr-1 size-3.5 animate-spin" />
                                Update
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
