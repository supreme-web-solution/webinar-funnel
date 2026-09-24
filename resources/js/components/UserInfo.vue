<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    variant?: 'default' | 'sidebar';
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    variant: 'default',
});

const { getInitials } = useInitials();

const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);

const isSidebar = computed(() => props.variant === 'sidebar');
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback
            class="rounded-lg"
            :class="isSidebar ? 'bg-[#2563EB]/35 text-blue-50' : 'bg-[#DBEAFE] text-[#1D4ED8]'"
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span
            class="truncate font-medium"
            :class="isSidebar ? 'text-blue-50' : 'text-foreground'"
        >
            {{ user.name }}
        </span>
        <span
            v-if="showEmail"
            class="truncate text-xs"
            :class="isSidebar ? 'text-blue-200/55' : 'text-muted-foreground'"
        >
            {{ user.email }}
        </span>
    </div>
</template>
