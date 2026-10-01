<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { ref } from 'vue';
import EmployeeAvatar from '@/components/command-center/EmployeeAvatar.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    employeeName: string;
    employeeAvatar?: string | null;
    configured: boolean;
    linkedPhone: string | null;
    pairing: {
        code: string;
        phone: string | null;
        wa_link: string;
        qr_url: string;
    } | null;
}>();

const emit = defineEmits<{
    setup: [];
    disconnect: [];
}>();

const copied = ref<'phone' | 'code' | null>(null);

async function copy(value: string, key: 'phone' | 'code'): Promise<void> {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = key;
        window.setTimeout(() => {
            if (copied.value === key) copied.value = null;
        }, 1600);
    } catch {
        copied.value = null;
    }
}
</script>

<template>
    <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
        <div class="mb-1 flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <Icon icon="simple-icons:whatsapp" class="size-4 text-[#25D366]" />
                <p class="text-sm font-semibold text-foreground">WhatsApp</p>
            </div>
            <Button v-if="!linkedPhone" size="sm" variant="outline" class="cursor-pointer" @click="emit('setup')">
                Setup
            </Button>
        </div>
        <p class="text-xs text-muted-foreground">Same {{ employeeName }} brain from your phone.</p>

        <div v-if="linkedPhone" class="mt-3 space-y-2">
            <p class="rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
                Linked to {{ linkedPhone }}
            </p>
            <Button
                size="sm"
                variant="destructive"
                class="w-full cursor-pointer bg-red-600 text-white hover:bg-red-700"
                @click="emit('disconnect')"
            >
                Disconnect WhatsApp
            </Button>
        </div>

        <div v-else-if="pairing" class="mt-4">
            <p class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
                <Icon icon="heroicons:device-phone-mobile" class="size-3.5 text-[#25D366]" />
                Link from your phone
            </p>

            <div class="mx-auto w-full max-w-[250px] rounded-[1.7rem] border-[7px] border-zinc-900 bg-[#efeae2] shadow-lg">
                <div class="flex items-center gap-2 bg-[#075E54] px-3 py-2.5 text-white">
                    <EmployeeAvatar
                        size="size-8"
                        :src="employeeAvatar"
                        :alt="employeeName"
                        :online="false"
                    />
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm font-semibold">{{ employeeName }}</p>
                        <p class="text-[10px] text-emerald-200">online</p>
                    </div>
                </div>

                <div class="space-y-2.5 px-2.5 py-3">
                    <div class="rounded-lg rounded-tl-sm bg-white px-2.5 py-2 text-[11px] leading-snug text-zinc-700 shadow-sm">
                        Most people connect from the WhatsApp app on their phone — scan the QR or copy the number and code below.
                    </div>

                    <div v-if="pairing.qr_url" class="rounded-lg bg-white px-3 py-2.5 text-center shadow-sm">
                        <img
                            :src="pairing.qr_url"
                            alt="WhatsApp pairing QR"
                            class="mx-auto size-32"
                        />
                        <p class="mt-1.5 flex items-center justify-center gap-1 text-[10px] text-zinc-500">
                            <Icon icon="heroicons:device-phone-mobile" class="size-3" />
                            Scan with your camera
                        </p>
                    </div>

                    <ol class="space-y-2 rounded-lg bg-white px-2.5 py-2.5 text-[11px] leading-snug text-zinc-700 shadow-sm">
                        <li class="flex gap-1.5">
                            <span class="font-semibold text-[#128C7E]">1.</span>
                            <span>Open WhatsApp on your phone.</span>
                        </li>
                        <li class="flex items-start gap-1.5">
                            <span class="font-semibold text-[#128C7E]">2.</span>
                            <span class="min-w-0 flex-1">
                                Chat with
                                <span class="font-semibold text-zinc-900">{{ pairing.phone || 'the business number' }}</span>
                            </span>
                            <button
                                v-if="pairing.phone"
                                type="button"
                                class="shrink-0 cursor-pointer text-[#128C7E]"
                                :title="copied === 'phone' ? 'Copied' : 'Copy number'"
                                @click="copy(pairing.phone, 'phone')"
                            >
                                <Icon :icon="copied === 'phone' ? 'heroicons:check' : 'heroicons:square-2-stack'" class="size-3.5" />
                            </button>
                        </li>
                        <li class="flex items-start gap-1.5">
                            <span class="font-semibold text-[#128C7E]">3.</span>
                            <span class="min-w-0 flex-1">
                                Send
                                <span class="font-semibold text-[#128C7E]">{{ pairing.code }}</span>
                            </span>
                            <button
                                type="button"
                                class="shrink-0 cursor-pointer text-[#128C7E]"
                                :title="copied === 'code' ? 'Copied' : 'Copy code'"
                                @click="copy(pairing.code, 'code')"
                            >
                                <Icon :icon="copied === 'code' ? 'heroicons:check' : 'heroicons:square-2-stack'" class="size-3.5" />
                            </button>
                        </li>
                    </ol>
                </div>

                <p v-if="pairing.wa_link" class="border-t border-black/5 px-3 py-2 text-center text-[10px] text-zinc-500">
                    WhatsApp on this computer?
                    <a :href="pairing.wa_link" target="_blank" rel="noopener noreferrer" class="font-medium text-[#128C7E] underline">
                        Open here
                    </a>
                </p>
            </div>

            <p class="mt-3 text-center text-[11px] leading-relaxed text-muted-foreground">
                On your phone's WhatsApp app, message
                <span class="font-medium text-foreground">{{ pairing.phone || 'the business number' }}</span>
                with code
                <span class="font-medium text-foreground">{{ pairing.code }}</span>
            </p>
        </div>

        <p v-else-if="!configured" class="mt-3 text-xs text-muted-foreground/80">
            Add ZERNIO_API_KEY and your business WhatsApp number (e.g. ZERNIO_FROM_NUMBER) in .env, then click Setup.
        </p>
        <p v-else-if="!linkedPhone" class="mt-3 text-xs text-muted-foreground">
            Click Setup to get a code and link this number.
        </p>
    </div>
</template>
