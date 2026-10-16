<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="space-y-4 text-center">
        <p class="text-sm text-muted-foreground">
            Two-factor authentication is no longer required. Sign in with your email and password.
        </p>
        <Button as-child>
            <Link :href="login()">Back to login</Link>
        </Button>
    </div>
</template>
