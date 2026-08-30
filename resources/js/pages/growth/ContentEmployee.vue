<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    phase: number;
    note: string;
    links: Array<{ label: string; href: string }>;
}>();
</script>

<template>
    <Head title="Content Employee" />
    <div class="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold">AI Content Employee</h1>
            <p class="text-sm text-muted-foreground">Phase {{ phase }} — {{ note }}</p>
        </div>
        <Card>
            <CardHeader><CardTitle>Use existing modules now</CardTitle></CardHeader>
            <CardContent class="space-y-2">
                <Link v-for="l in links" :key="l.href" :href="l.href" class="block text-indigo-600 underline">
                    {{ l.label }}
                </Link>
            </CardContent>
        </Card>
    </div>
</template>
