<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    domains: Array<{ id: number; domain: string; status: string; verified_at: string | null }>;
    phase: number;
    note: string;
}>();

const form = useForm({ domain: '' });
</script>

<template>
    <Head title="Custom Domains" />
    <div class="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold">Custom Domains</h1>
            <p class="text-sm text-muted-foreground">Phase {{ phase }} scaffold — {{ note }}</p>
        </div>
        <Card>
            <CardHeader><CardTitle>Reserve a domain</CardTitle></CardHeader>
            <CardContent class="flex gap-2">
                <div class="flex-1 space-y-2">
                    <Label>Domain</Label>
                    <Input v-model="form.domain" placeholder="offers.example.com" />
                </div>
                <Button class="mt-7" :disabled="form.processing" @click="form.post('/growth/domains')">Add</Button>
            </CardContent>
        </Card>
        <Card v-for="d in domains" :key="d.id">
            <CardContent class="p-4 text-sm flex justify-between">
                <span class="font-medium">{{ d.domain }}</span>
                <span class="text-muted-foreground">{{ d.status }}</span>
            </CardContent>
        </Card>
    </div>
</template>
