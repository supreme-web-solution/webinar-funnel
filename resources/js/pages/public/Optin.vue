<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, router } from '@inertiajs/vue3';
import { onBeforeMount, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    funnel: {
        name: string;
        slug: string;
        owner: string;
    };
    pageHtml?: string | null;
    pageCss?: string | null;
    /** Legacy fallback for funnels using old hero-object schema */
    page?: {
        hero?: { headline?: string; subheadline?: string; cta?: string };
    };
}>();

const GFONTS_URL =
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900' +
    '&family=Roboto:wght@400;700&family=Open+Sans:wght@400;600;700' +
    '&family=Lato:wght@400;700&family=Montserrat:wght@400;600;700;900' +
    '&family=Poppins:wght@400;600;700;900&family=Raleway:wght@400;600;700' +
    '&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,700' +
    '&family=Plus+Jakarta+Sans:wght@400;600;700&family=Outfit:wght@400;600;700;900' +
    '&family=Nunito:wght@400;600;700&family=Oswald:wght@400;500;600;700' +
    '&family=Source+Sans+3:wght@400;600;700' +
    '&family=Playfair+Display:ital,wght@0,400;0,700;1,400' +
    '&family=Merriweather:ital,wght@0,400;0,700;1,400' +
    '&family=Lora:ital,wght@0,400;0,700;1,400&display=swap';

const pageContainer = ref<HTMLElement | null>(null);
const submitting    = ref(false);
const submitError   = ref('');

/* ── Injected <head> elements (kept for cleanup) ─────── */
let elPreconnect1: HTMLLinkElement | null  = null;
let elPreconnect2: HTMLLinkElement | null  = null;
let elFontLink: HTMLLinkElement | null     = null;
let elReset: HTMLStyleElement | null       = null;
let elPageCss: HTMLStyleElement | null     = null;

/**
 * Inject all styles BEFORE the component's DOM is inserted so the browser
 * never paints unstyled HTML (eliminates FOUC completely).
 */
onBeforeMount(() => {
    if (!props.pageHtml) {
        return;
    }

    elPreconnect1 = document.createElement('link');
    elPreconnect1.rel = 'preconnect';
    elPreconnect1.href = 'https://fonts.googleapis.com';
    document.head.appendChild(elPreconnect1);

    elPreconnect2 = document.createElement('link');
    elPreconnect2.rel = 'preconnect';
    elPreconnect2.href = 'https://fonts.gstatic.com';
    elPreconnect2.crossOrigin = 'anonymous';
    document.head.appendChild(elPreconnect2);

    elFontLink = document.createElement('link');
    elFontLink.rel = 'stylesheet';
    elFontLink.href = GFONTS_URL;
    document.head.appendChild(elFontLink);

    /* Strip app-shell margins so the page fills the viewport edge-to-edge */
    elReset = document.createElement('style');
    elReset.textContent = 'html,body,#app{margin:0!important;padding:0!important;width:100%!important;background:transparent!important;}';
    document.head.appendChild(elReset);

    if (props.pageCss) {
        elPageCss = document.createElement('style');
        elPageCss.textContent = props.pageCss;
        document.head.appendChild(elPageCss);
    }
});

onUnmounted(() => {
    elPreconnect1?.remove();
    elPreconnect2?.remove();
    elFontLink?.remove();
    elReset?.remove();
    elPageCss?.remove();
});

/* ── Intercept the form and submit via Inertia ─────────── */
const wireForm = (): void => {
    if (!pageContainer.value) {
        return;
    }

    const form =
        pageContainer.value.querySelector<HTMLFormElement>('form[data-locked-form]') ??
        pageContainer.value.querySelector<HTMLFormElement>('form');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (e: Event) => {
        e.preventDefault();

        if (submitting.value) {
            return;
        }

        const nameEl  = form.querySelector<HTMLInputElement>('input[name="name"]');
        const emailEl = form.querySelector<HTMLInputElement>('input[name="email"]');
        const name    = nameEl?.value.trim() ?? '';
        const email   = emailEl?.value.trim() ?? '';

        if (!name || !email) {
            submitError.value = 'Please fill in all fields.';

            return;
        }

        submitError.value = '';
        submitting.value  = true;

        const btn = form.querySelector<HTMLButtonElement>('button[type="submit"]');

        if (btn) {
            btn.disabled    = true;
            btn.textContent = 'Registering…';
        }

        router.post(
            `/${props.funnel.owner}/${props.funnel.slug}/optin`,
            { name, email },
            {
                onError: () => {
                    submitting.value  = false;
                    submitError.value = 'Something went wrong. Please try again.';

                    if (btn) {
                        btn.disabled    = false;
                        btn.textContent = 'Try Again';
                    }
                },
            },
        );
    });
};

onMounted(() => {
    wireForm();
});
</script>

<template>
    <Head :title="page?.hero?.headline ?? funnel.name" />

    <!--
        GrapesJS pages: styles are injected into <head> via onBeforeMount so the
        browser never paints unstyled HTML (no FOUC).
    -->
    <div
        v-if="pageHtml"
        ref="pageContainer"
        v-html="pageHtml"
        style="margin:0;padding:0;width:100%;min-height:100vh;overflow-x:hidden;"
    />

    <!--
        Legacy fallback: old funnels that still use the hero-object schema
        get the branded opt-in page template.
    -->
    <div v-else class="min-h-screen bg-gradient-to-br from-slate-900 via-[#0d1a2e] to-slate-900 flex flex-col items-center justify-center p-4 relative overflow-hidden">

        <!-- Decorative blobs -->
        <div class="pointer-events-none absolute -top-32 left-1/2 -translate-x-1/2 size-[600px] rounded-full opacity-20 blur-[120px]" style="background: radial-gradient(circle, #40E0D0, transparent 70%)" />
        <div class="pointer-events-none absolute bottom-0 right-0 size-[400px] rounded-full opacity-10 blur-[100px]" style="background: radial-gradient(circle, #FFAD00, transparent 70%)" />

        <!-- Logo -->
        <div class="mb-8 flex items-center gap-2">
            <div class="flex size-8 items-center justify-center rounded-lg" style="background:rgba(64,224,208,0.2)">
                <Icon icon="heroicons:video-camera" class="size-4" style="color:#40E0D0" />
            </div>
            <span class="text-sm font-semibold text-white/70">DFY Webinar Funnels</span>
        </div>

        <!-- Card -->
        <div class="w-full max-w-lg relative">
            <div class="absolute -inset-px rounded-2xl opacity-40" style="background: linear-gradient(135deg, #40E0D0, #FFAD00); border-radius: 1rem; filter: blur(1px)" />
            <div class="relative rounded-2xl bg-[#0d1424] border border-white/10 overflow-hidden shadow-2xl">
                <div class="h-1 w-full" style="background: linear-gradient(90deg, #40E0D0, #FFAD00)" />
                <div class="p-8 space-y-6">
                    <div class="flex justify-center">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-500/30 bg-rose-600/15 px-3 py-1 text-xs font-bold uppercase tracking-widest text-rose-400">
                            <span class="size-1.5 rounded-full bg-rose-500 animate-pulse" />
                            Free Webinar
                        </span>
                    </div>
                    <div class="text-center space-y-3">
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white leading-tight">
                            {{ page?.hero?.headline ?? funnel.name }}
                        </h1>
                        <p class="text-white/55 text-sm leading-relaxed">
                            {{ page?.hero?.subheadline ?? 'Register with your name and email address to secure your spot.' }}
                        </p>
                    </div>

                    <form ref="pageContainer" class="space-y-3" @submit.prevent="() => {}">
                        <div class="relative">
                            <Icon icon="heroicons:user" class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-white/30 pointer-events-none" />
                            <input
                                id="legacy-name"
                                type="text"
                                name="name"
                                placeholder="Your full name"
                                required
                                class="w-full rounded-xl border border-white/10 bg-white/5 pl-10 pr-4 py-3 text-sm text-white placeholder:text-white/30 focus:border-primary/50 focus:outline-none transition-all"
                            />
                        </div>
                        <div class="relative">
                            <Icon icon="heroicons:envelope" class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-white/30 pointer-events-none" />
                            <input
                                id="legacy-email"
                                type="email"
                                name="email"
                                placeholder="Your best email address"
                                required
                                class="w-full rounded-xl border border-white/10 bg-white/5 pl-10 pr-4 py-3 text-sm text-white placeholder:text-white/30 focus:border-primary/50 focus:outline-none transition-all"
                            />
                        </div>
                        <p v-if="submitError" class="text-xs text-rose-400">{{ submitError }}</p>
                        <button
                            type="submit"
                            class="group relative w-full overflow-hidden rounded-xl px-6 py-3.5 text-sm font-bold disabled:opacity-60"
                            :disabled="submitting"
                            style="background: linear-gradient(135deg, #40E0D0, #2dc4b5); color: #0a0f1e"
                        >
                            <span class="flex items-center justify-center gap-2">
                                <Icon v-if="submitting" icon="heroicons:arrow-path" class="size-4 animate-spin" />
                                <Icon v-else icon="heroicons:rocket-launch" class="size-4" />
                                {{ submitting ? 'Registering…' : (page?.hero?.cta ?? 'Yes! Reserve My Spot →') }}
                            </span>
                        </button>
                        <p class="text-center text-[0.65rem] text-white/30">
                            We respect your privacy. Unsubscribe at any time.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
