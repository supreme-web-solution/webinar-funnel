<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';
import { nextTick, onMounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    funnel: {
        id: number;
        name: string;
        slug: string;
        status: string;
        settings: {
            webinar_title?: string | null;
            webinar_description?: string | null;
            video_url?: string | null;
            chat_mode: string;
            allow_replay: boolean;
            double_opt_in: boolean;
            chat_seed_messages?: Array<{ author: string; message: string }>;
        } | null;
        pages: Array<{
            page_type: 'optin' | 'webinar';
            schema: Record<string, unknown>;
        }>;
        integrations: Array<{ integration_account: { id: number; name: string; provider: string } }>;
    };
    integrationAccounts: Array<{ id: number; name: string; provider: string }>;
    conversationSummaries: Array<{
        conversation_key: string;
        attendee_name: string;
        attendee_email?: string | null;
        latest_message?: string | null;
        message_count: number;
    }>;
    publicLinks: {
        optin: string;
        webinar: string;
    };
}>();

const optinPage = props.funnel.pages.find((p) => p.page_type === 'optin');

/* ─── Editor refs ──────────────────────────────────────────────────────── */
const editorContainer = ref<HTMLElement | null>(null);
const blocksContainer = ref<HTMLElement | null>(null);
const stylesContainer = ref<HTMLElement | null>(null);
const gjsEditor        = ref<any>(null);
const showStyles       = ref(true);
const activeDevice     = ref<'desktop' | 'mobile'>('desktop');
const isFullscreen     = ref(false);

const copiedLink    = ref<'optin' | 'webinar' | null>(null);
const savingPage    = ref(false);
const savingSettings = ref(false);
const publishing    = ref(false);
const activeTab     = ref('optin');

/* Refresh canvas when returning to optin tab (hidden iframe collapses to 0×0) */
watch(activeTab, (tab) => {
    if (tab !== 'optin') {
        return;
    }

    nextTick(() => {
        if (gjsEditor.value) {
            gjsEditor.value.refresh();
        }
    });
});

/* ─── Toolbar actions ──────────────────────────────────────────────────── */
const editorUndo = () => gjsEditor.value?.runCommand('core:undo');
const editorRedo = () => gjsEditor.value?.runCommand('core:redo');

function setDevice(device: 'desktop' | 'mobile'): void {
    activeDevice.value = device;
    gjsEditor.value?.setDevice(device === 'desktop' ? 'Desktop' : 'Mobile');
}

function toggleFullscreen(): void {
    isFullscreen.value = !isFullscreen.value;
    // Recalculate canvas dimensions after the DOM updates
    nextTick(() => {
        gjsEditor.value?.refresh();
    });
}

const pageForm = useForm<{ page_type: 'optin' | 'webinar'; schema: any }>({
    page_type: 'optin',
    schema: optinPage?.schema ?? {},
});

const publishForm = useForm({});

const settingsForm = useForm<{
    webinar_title: string;
    webinar_description: string;
    video_url: string;
    chat_mode: string;
    allow_replay: boolean;
    double_opt_in: boolean;
    chat_seed_messages: Array<{ author: string; message: string }>;
    branding: { primary: string; secondary: string };
    integration_account_ids: number[];
}>({
    webinar_title: props.funnel.settings?.webinar_title ?? '',
    webinar_description: props.funnel.settings?.webinar_description ?? '',
    video_url: props.funnel.settings?.video_url ?? '',
    chat_mode: props.funnel.settings?.chat_mode ?? 'simulated',
    allow_replay: props.funnel.settings?.allow_replay ?? true,
    double_opt_in: props.funnel.settings?.double_opt_in ?? false,
    chat_seed_messages: props.funnel.settings?.chat_seed_messages ?? [],
    branding: { primary: '#111827', secondary: '#F9FAFB' },
    integration_account_ids: props.funnel.integrations.map((i) => i.integration_account.id),
});

const savePage = (): void => {
    if (editorContainer.value && (editorContainer.value as any).__gjsEditor) {
        const editor = (editorContainer.value as any).__gjsEditor;

        /*
         * Only store plain strings — never pass GrapesJS component/style
         * manager objects into the form, as their internal reactive proxies
         * cause Inertia's hasFiles() serialiser to overflow the call stack.
         */
        pageForm.schema = {
            html: String(editor.getHtml()),
            css:  String(editor.getCss()),
        };
    }

    savingPage.value = true;
    pageForm.patch(`/funnels/${props.funnel.id}/pages`, {
        onFinish: () => {
            savingPage.value = false;
        },
    });
};

const saveSettings = (): void => {
    savingSettings.value = true;
    settingsForm.patch(`/funnels/${props.funnel.id}/settings`, {
        onFinish: () => {
            savingSettings.value = false;
        },
    });
};

const publish = (): void => {
    publishing.value = true;
    publishForm.post(`/funnels/${props.funnel.id}/publish`, {
        onFinish: () => {
            publishing.value = false;
        },
    });
};

const copyLink = async (type: 'optin' | 'webinar'): Promise<void> => {
    await navigator.clipboard.writeText(props.publicLinks[type]);
    copiedLink.value = type;
    setTimeout(() => {
        copiedLink.value = null;
    }, 2000);
};

const espProviderIcon: Record<string, string> = {
    mailchimp: 'simple-icons:mailchimp',
    getresponse: 'simple-icons:getresponse',
    activecampaign: 'simple-icons:activecampaign',
    convertkit: 'simple-icons:convertkit',
    aweber: 'logos:aweber',
    drip: 'simple-icons:drip',
};

function providerIcon(provider: string): string {
    return espProviderIcon[provider.toLowerCase()] ?? 'heroicons:envelope';
}

onMounted(() => {
    if (!editorContainer.value || !blocksContainer.value || !stylesContainer.value) {
        return;
    }

    const schema = pageForm.schema as any;

    const initialHtml = schema?.html ?? `
<div class="dfy-page">
  <section class="dfy-hero">
    <div class="dfy-inner">
      <span class="dfy-badge">🎓 FREE WEBINAR</span>
      <h1 class="dfy-headline">${schema?.hero?.headline ?? 'Your Webinar Headline Here'}</h1>
      <p class="dfy-sub">${schema?.hero?.subheadline ?? 'Register below to secure your free spot.'}</p>
      <form class="dfy-form" data-locked-form="true">
        <input class="dfy-input" name="name" type="text" placeholder="Your full name" required />
        <input class="dfy-input" name="email" type="email" placeholder="Your best email" required />
        <button class="dfy-btn" type="submit">${schema?.hero?.cta ?? 'Reserve My Spot →'}</button>
      </form>
    </div>
  </section>
</div>`;

    const initialCss = schema?.css ?? `
.dfy-page{min-height:100vh;background:linear-gradient(140deg,#060d1a 0%,#0d2039 100%);display:flex;align-items:center;justify-content:center;padding:40px 16px}
.dfy-inner{max-width:520px;width:100%;text-align:center}
.dfy-badge{display:inline-block;background:rgba(255,80,80,.15);border:1px solid rgba(255,80,80,.3);color:#ff7070;padding:6px 16px;border-radius:100px;font-size:11px;font-weight:700;margin-bottom:24px}
.dfy-headline{font-size:2.2rem;font-weight:900;color:#fff;line-height:1.2;margin-bottom:14px}
.dfy-sub{font-size:1rem;color:rgba(255,255,255,.6);line-height:1.7;margin-bottom:28px}
.dfy-form{background:rgba(255,255,255,.05);border:1px solid rgba(64,224,208,.2);border-radius:16px;padding:28px}
.dfy-input{display:block;width:100%;padding:13px 16px;border:1px solid rgba(255,255,255,.15);border-radius:8px;background:rgba(255,255,255,.07);color:#fff;font-size:15px;outline:none;margin-bottom:12px}
.dfy-input::placeholder{color:rgba(255,255,255,.35)}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#40E0D0,#2dc4b5);color:#060d1a;font-size:16px;font-weight:800;border:none;border-radius:8px;cursor:pointer}`;

    /* ── Inline SVG icons for blocks ─────────────────────────────────────── */
    const svg = (path: string) =>
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;

    /* ── GrapesJS init ───────────────────────────────────────────────────── */
    const editor = grapesjs.init({
        container: editorContainer.value,
        fromElement: false,
        height: '100%',
        width: 'auto',
        storageManager: false,
        components: initialHtml,
        style: initialCss,

        /* Disable ALL default panels — we build our own toolbar in Vue */
        panels: { defaults: [] },

        deviceManager: {
            devices: [
                { name: 'Desktop', width: '' },
                { name: 'Mobile',  width: '375px', widthMedia: '480px' },
            ],
        },

        /* Inject into the canvas iframe: fill height + load Google Fonts */
        canvas: {
            styles: [
                'data:text/css,html,body{min-height:100%;height:100%;}',
                'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&family=Roboto:wght@400;700&family=Open+Sans:wght@400;600;700&family=Lato:wght@400;700&family=Montserrat:wght@400;600;700;900&family=Poppins:wght@400;600;700;900&family=Raleway:wght@400;600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,700&family=Plus+Jakarta+Sans:wght@400;600;700&family=Outfit:wght@400;600;700;900&family=Nunito:wght@400;600;700&family=Oswald:wght@400;500;600;700&family=Source+Sans+3:wght@400;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Lora:ital,wght@0,400;0,700;1,400&display=swap',
            ],
        },

        /* Put the block picker in our custom left sidebar */
        blockManager: {
            appendTo: blocksContainer.value,
        },

        /* Put the style editor in our custom right sidebar */
        styleManager: ({
            appendTo: stylesContainer.value,
            sectors: [
                {
                    name: 'Typography', open: true,
                    properties: [
                        { label: 'Font Family', property: 'font-family', type: 'select', defaults: 'inherit',
                            options: [
                                { value: 'inherit',                                    name: '— Default —' },
                                /* ── Sans-serif ── */
                                { value: "'Inter', sans-serif",                        name: 'Inter' },
                                { value: "'Roboto', sans-serif",                       name: 'Roboto' },
                                { value: "'Open Sans', sans-serif",                    name: 'Open Sans' },
                                { value: "'Lato', sans-serif",                         name: 'Lato' },
                                { value: "'Montserrat', sans-serif",                   name: 'Montserrat' },
                                { value: "'Poppins', sans-serif",                      name: 'Poppins' },
                                { value: "'Raleway', sans-serif",                      name: 'Raleway' },
                                { value: "'DM Sans', sans-serif",                      name: 'DM Sans' },
                                { value: "'Plus Jakarta Sans', sans-serif",            name: 'Plus Jakarta Sans' },
                                { value: "'Outfit', sans-serif",                       name: 'Outfit' },
                                { value: "'Nunito', sans-serif",                       name: 'Nunito' },
                                { value: "'Oswald', sans-serif",                       name: 'Oswald' },
                                { value: "'Source Sans 3', sans-serif",                name: 'Source Sans 3' },
                                /* ── Serif ── */
                                { value: "'Playfair Display', serif",                  name: 'Playfair Display' },
                                { value: "'Merriweather', serif",                      name: 'Merriweather' },
                                { value: "'Lora', serif",                              name: 'Lora' },
                                { value: "Georgia, serif",                             name: 'Georgia' },
                                /* ── System ── */
                                { value: "Arial, Helvetica, sans-serif",              name: 'Arial' },
                                { value: "'Helvetica Neue', Helvetica, sans-serif",   name: 'Helvetica Neue' },
                                { value: "'Trebuchet MS', sans-serif",                name: 'Trebuchet MS' },
                                { value: "'Times New Roman', Times, serif",           name: 'Times New Roman' },
                            ],
                        },
                        { label: 'Size',        property: 'font-size',   type: 'integer', units: ['px','rem','em','%'], defaults: '16px' },
                        { label: 'Weight',      property: 'font-weight', type: 'select',  defaults: '400',
                            options: [{ value: '300', name: 'Light' }, { value: '400', name: 'Regular' }, { value: '600', name: 'Semi-Bold' }, { value: '700', name: 'Bold' }, { value: '900', name: 'Black' }] },
                        { label: 'Color',       property: 'color',       type: 'color' },
                        { label: 'Align',       property: 'text-align',  type: 'radio',   defaults: 'left',
                            options: [{ value: 'left', name: 'L' }, { value: 'center', name: 'C' }, { value: 'right', name: 'R' }] },
                        { label: 'Line Height', property: 'line-height', type: 'integer', units: ['','px','em'], defaults: '1.5' },
                    ],
                },
                {
                    name: 'Background', open: false,
                    properties: [
                        { label: 'Color',    property: 'background-color', type: 'color' },
                    ],
                },
                {
                    name: 'Spacing', open: false,
                    properties: [
                        { property: 'padding', type: 'composite',
                            properties: [
                                { property: 'padding-top',    type: 'integer', units: ['px','%','em'], defaults: '0' },
                                { property: 'padding-right',  type: 'integer', units: ['px','%','em'], defaults: '0' },
                                { property: 'padding-bottom', type: 'integer', units: ['px','%','em'], defaults: '0' },
                                { property: 'padding-left',   type: 'integer', units: ['px','%','em'], defaults: '0' },
                            ]},
                        { property: 'margin', type: 'composite',
                            properties: [
                                { property: 'margin-top',    type: 'integer', units: ['px','%','em','auto'], defaults: '0' },
                                { property: 'margin-right',  type: 'integer', units: ['px','%','em','auto'], defaults: '0' },
                                { property: 'margin-bottom', type: 'integer', units: ['px','%','em','auto'], defaults: '0' },
                                { property: 'margin-left',   type: 'integer', units: ['px','%','em','auto'], defaults: '0' },
                            ]},
                    ],
                },
                {
                    name: 'Border', open: false,
                    properties: [
                        { label: 'Radius', property: 'border-radius', type: 'integer', units: ['px','%'] },
                        { label: 'Width',  property: 'border-width',  type: 'integer', units: ['px'] },
                        { label: 'Color',  property: 'border-color',  type: 'color' },
                        { label: 'Style',  property: 'border-style',  type: 'select',
                            options: [{ value: 'none', name: 'None' }, { value: 'solid', name: 'Solid' }, { value: 'dashed', name: 'Dashed' }, { value: 'dotted', name: 'Dotted' }] },
                    ],
                },
                {
                    name: 'Size', open: false,
                    properties: [
                        { label: 'Width',     property: 'width',     type: 'integer', units: ['px','%','vw','auto'] },
                        { label: 'Max Width', property: 'max-width', type: 'integer', units: ['px','%','none'] },
                        { label: 'Height',    property: 'height',    type: 'integer', units: ['px','%','vh','auto'] },
                    ],
                },
            ],
        } as any),
    });

    /* ── Pre-built drag-and-drop blocks ──────────────────────────────────── */
    const BLOCKS = [
        {
            id: 'heading', label: 'Heading', category: 'Content',
            media: svg('<path d="M4 6h16M4 12h10M4 18h7"/>'),
            content: '<h2 style="font-size:2rem;font-weight:800;color:#111827;margin:0 0 8px;line-height:1.2;">Your Headline Here</h2>',
        },
        {
            id: 'paragraph', label: 'Paragraph', category: 'Content',
            media: svg('<path d="M4 6h16M4 10h16M4 14h12M4 18h9"/>'),
            content: '<p style="font-size:1rem;color:#4B5563;line-height:1.7;margin:0 0 16px;">Click to edit this paragraph. Write something compelling about your webinar or offer.</p>',
        },
        {
            id: 'button', label: 'Button', category: 'Content',
            media: svg('<rect x="2" y="7" width="20" height="10" rx="3"/><path d="M9 12h6"/>'),
            content: '<a href="#" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#40E0D0,#2dc4b5);color:#060d1a;font-size:15px;font-weight:700;border-radius:8px;text-decoration:none;">Register Now →</a>',
        },
        {
            id: 'badge', label: 'Badge', category: 'Content',
            media: svg('<rect x="3" y="8" width="18" height="8" rx="4"/><path d="M9 12h6"/>'),
            content: '<span style="display:inline-block;background:rgba(64,224,208,0.12);border:1px solid rgba(64,224,208,0.3);color:#0d9488;padding:6px 16px;border-radius:100px;font-size:12px;font-weight:700;letter-spacing:0.06em;">🎓 FREE WEBINAR</span>',
        },
        {
            id: 'list', label: 'Check List', category: 'Content',
            media: svg('<path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138"/>'),
            content: `<ul style="list-style:none;padding:0;margin:0;">
<li style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;font-size:15px;color:#374151;"><span style="color:#40E0D0;font-weight:800;font-size:16px;line-height:1.5;">✓</span>First benefit or feature here</li>
<li style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;font-size:15px;color:#374151;"><span style="color:#40E0D0;font-weight:800;font-size:16px;line-height:1.5;">✓</span>Second benefit or feature here</li>
<li style="display:flex;align-items:flex-start;gap:10px;font-size:15px;color:#374151;"><span style="color:#40E0D0;font-weight:800;font-size:16px;line-height:1.5;">✓</span>Third benefit or feature here</li>
</ul>`,
        },
        {
            id: 'testimonial', label: 'Testimonial', category: 'Content',
            media: svg('<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>'),
            content: `<blockquote style="background:rgba(64,224,208,0.07);border-left:4px solid #40E0D0;padding:20px 24px;border-radius:0 12px 12px 0;margin:0;">
<p style="font-size:1rem;color:#374151;font-style:italic;line-height:1.7;margin:0 0 10px;">"This webinar completely changed how I approach my business. Practical, actionable, and incredibly valuable!"</p>
<cite style="font-size:0.85rem;font-weight:600;color:#111827;font-style:normal;">— Jane Smith, CEO at Example Co.</cite>
</blockquote>`,
        },
        {
            id: 'image', label: 'Image', category: 'Media',
            media: svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'),
            content: '<img src="https://placehold.co/800x400/40E0D0/060d1a?text=Your+Image" style="max-width:100%;height:auto;display:block;border-radius:10px;" alt="Image"/>',
        },
        {
            id: 'video', label: 'Video', category: 'Media',
            media: svg('<rect x="2" y="4" width="20" height="16" rx="2"/><polygon points="10 9 16 12 10 15 10 9"/>'),
            content: '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:10px;background:#000;"><iframe style="position:absolute;top:0;left:0;width:100%;height:100%;" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen></iframe></div>',
        },
        {
            id: 'cols-2', label: '2 Columns', category: 'Layout',
            media: svg('<rect x="2" y="4" width="9" height="16" rx="1.5"/><rect x="13" y="4" width="9" height="16" rx="1.5"/>'),
            content: '<div style="display:flex;gap:16px;"><div style="flex:1;padding:20px 16px;background:rgba(0,0,0,0.03);border-radius:8px;min-height:80px;"><p style="color:#374151;margin:0;">Column 1</p></div><div style="flex:1;padding:20px 16px;background:rgba(0,0,0,0.03);border-radius:8px;min-height:80px;"><p style="color:#374151;margin:0;">Column 2</p></div></div>',
        },
        {
            id: 'cols-3', label: '3 Columns', category: 'Layout',
            media: svg('<rect x="2" y="4" width="6" height="16" rx="1.5"/><rect x="9" y="4" width="6" height="16" rx="1.5"/><rect x="16" y="4" width="6" height="16" rx="1.5"/>'),
            content: '<div style="display:flex;gap:12px;"><div style="flex:1;padding:16px 12px;background:rgba(0,0,0,0.03);border-radius:8px;min-height:70px;"><p style="color:#374151;margin:0;font-size:14px;">Col 1</p></div><div style="flex:1;padding:16px 12px;background:rgba(0,0,0,0.03);border-radius:8px;min-height:70px;"><p style="color:#374151;margin:0;font-size:14px;">Col 2</p></div><div style="flex:1;padding:16px 12px;background:rgba(0,0,0,0.03);border-radius:8px;min-height:70px;"><p style="color:#374151;margin:0;font-size:14px;">Col 3</p></div></div>',
        },
        {
            id: 'section', label: 'Section', category: 'Layout',
            media: svg('<rect x="2" y="3" width="20" height="18" rx="2"/><path d="M2 9h20"/>'),
            content: '<section style="padding:60px 24px;background:linear-gradient(135deg,#060d1a,#0d2039);text-align:center;"><h2 style="font-size:2rem;font-weight:900;color:#fff;margin:0 0 12px;">Section Heading</h2><p style="font-size:1rem;color:rgba(255,255,255,0.6);margin:0 auto;max-width:480px;line-height:1.7;">Add your description here to tell visitors what this section is about.</p></section>',
        },
        {
            id: 'divider', label: 'Divider', category: 'Layout',
            media: svg('<line x1="5" y1="12" x2="19" y2="12"/>'),
            content: '<hr style="border:none;border-top:1px solid rgba(0,0,0,0.1);margin:24px 0;"/>',
        },
        {
            id: 'spacer', label: 'Spacer', category: 'Layout',
            media: svg('<path d="M12 3v18M5 8l7-5 7 5M5 16l7 5 7-5"/>'),
            content: '<div style="height:48px;"></div>',
        },
    ];

    BLOCKS.forEach((b) => {
        editor.BlockManager.add(b.id, { label: b.label, category: b.category, media: b.media, content: b.content });
    });

    /* ── Lock the opt-in form ─────────────────────────────────────────────── */
    editor.on('component:remove:before', (component: any, _remove: () => void, opts: any) => {
        if (component?.getAttributes()?.['data-locked-form']) {
            opts.abort = true;
        }
    });

    /* ── Inject a modern light theme over GrapesJS defaults ──────────────── */
    if (!document.getElementById('gjs-dfy-theme')) {
        const s = document.createElement('style');
        s.id = 'gjs-dfy-theme';
        s.textContent = `
/* ──────────────────────────────────────────────────────────────────────────
   DFY GrapesJS Light Theme — overrides the default dark gray UI
   ──────────────────────────────────────────────────────────────────────── */

/* Canvas — force full-width AND full-height fill of the container */
.gjs-cv-canvas {
  background: #111827 !important;
  width: 100% !important;
  height: 100% !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
}
.gjs-cv-canvas__frames {
  width: 100% !important;
  height: 100% !important;
}
/* Do NOT force width on frame-wrapper/frame — GrapesJS needs to set 375px for mobile */
.gjs-frame-wrapper { height: 100% !important; }
.gjs-frame          { height: 100% !important; min-height: 100% !important; }

/* Hide built-in GrapesJS panel bar (we replaced it with our Vue toolbar) */
.gjs-pn-panels { display: none !important; }

/* Block categories */
.gjs-block-category .gjs-title {
  background: transparent !important;
  color: #9ca3af !important;
  font-size: 10px !important;
  font-weight: 700 !important;
  letter-spacing: .08em !important;
  text-transform: uppercase !important;
  padding: 12px 10px 4px !important;
  border-bottom: 1px solid #f3f4f6 !important;
}
.gjs-block-category .gjs-caret-icon { color: #9ca3af !important; }

/* Block grid */
.gjs-blocks-c {
  display: grid !important;
  grid-template-columns: 1fr 1fr !important;
  gap: 5px !important;
  padding: 8px !important;
}

/* Single block tile */
.gjs-block {
  background: #fff !important;
  border: 1px solid #e5e7eb !important;
  border-radius: 8px !important;
  padding: 10px 4px 7px !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  gap: 5px !important;
  cursor: grab !important;
  transition: border-color .15s, box-shadow .15s, transform .15s !important;
  min-height: unset !important;
  width: auto !important;
}
.gjs-block:hover {
  border-color: #40E0D0 !important;
  background: rgba(64,224,208,.06) !important;
  transform: translateY(-1px) !important;
  box-shadow: 0 3px 10px rgba(64,224,208,.18) !important;
}
.gjs-block svg {
  width: 22px !important; height: 22px !important;
  color: #9ca3af !important;
  transition: color .15s !important;
}
.gjs-block:hover svg { color: #0d9488 !important; }

/* Block label */
.gjs-block-label {
  font-size: 10px !important;
  font-weight: 600 !important;
  color: #374151 !important;
  text-align: center !important;
  line-height: 1.2 !important;
}

/* Style manager sectors */
.gjs-sm-sector {
  border: none !important;
  border-bottom: 1px solid #f3f4f6 !important;
  background: transparent !important;
}
.gjs-sm-sector .gjs-sm-title {
  background: transparent !important;
  padding: 10px 12px 8px !important;
  font-size: 10px !important;
  font-weight: 700 !important;
  color: #9ca3af !important;
  text-transform: uppercase !important;
  letter-spacing: .06em !important;
  border: none !important;
}
.gjs-sm-properties { padding: 4px 10px 12px !important; }

/* Style inputs */
.gjs-field {
  background: #fff !important;
  border: 1px solid #e5e7eb !important;
  border-radius: 6px !important;
  color: #111827 !important;
  font-size: 12px !important;
}
.gjs-field:focus-within { border-color: #40E0D0 !important; box-shadow: 0 0 0 2px rgba(64,224,208,.15) !important; }
.gjs-sm-label { font-size: 11px !important; color: #6b7280 !important; font-weight: 500 !important; margin-bottom: 3px !important; }

/* Select element highlight */
.gjs-selected { outline: 2px solid #40E0D0 !important; }

/* Floating element toolbar (del, move, etc.) */
.gjs-toolbar { background: #111827 !important; border-radius: 8px !important; box-shadow: 0 4px 12px rgba(0,0,0,.3) !important; }
.gjs-toolbar-item { border-right: 1px solid rgba(255,255,255,.08) !important; }
.gjs-toolbar-item:hover { background: rgba(64,224,208,.15) !important; }
.gjs-toolbar-item svg { color: #fff !important; }

/* Drop placeholder */
.gjs-placeholder { background: #40E0D0 !important; opacity: .4 !important; }
.gjs-placeholder-int { background: #2dc4b5 !important; }

/* Scrollbar */
.gjs-blocks-c::-webkit-scrollbar, .gjs-sm-properties::-webkit-scrollbar { width: 3px !important; }
.gjs-blocks-c::-webkit-scrollbar-thumb, .gjs-sm-properties::-webkit-scrollbar-thumb { background: #d1d5db !important; border-radius: 2px !important; }
        `;
        document.head.appendChild(s);
    }

    gjsEditor.value = editor;
    (editorContainer.value as any).__gjsEditor = editor;
});
</script>

<template>
    <Head :title="`Edit — ${funnel.name}`" />

    <div class="flex flex-col gap-5 p-4 md:p-6 w-full max-w-screen-xl mx-auto">

        <!-- ── Page header ── -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3 min-w-0">
                <Button as-child variant="ghost" size="sm" class="shrink-0 text-muted-foreground h-8 px-2 -ml-1 mt-0.5">
                    <Link href="/dashboard">
                        <Icon icon="heroicons:arrow-left" class="size-4" />
                    </Link>
                </Button>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl font-bold tracking-tight text-foreground truncate">{{ funnel.name }}</h1>
                        <Badge
                            class="capitalize text-[0.65rem] px-2 py-0.5 shrink-0"
                            :class="funnel.status === 'published'
                                ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                : 'bg-amber-50 text-amber-700 border-amber-200'"
                        >
                            <span
                                v-if="funnel.status === 'published'"
                                class="mr-1 inline-block size-1.5 rounded-full bg-emerald-500"
                            />
                            {{ funnel.status }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">/{{ funnel.slug }}</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto">
                <Button as-child variant="outline" size="sm" class="h-8 text-xs gap-1.5">
                    <a :href="`/funnels/${funnel.id}/chat`">
                        <Icon icon="heroicons:chat-bubble-left-right" class="size-3.5" />
                        Chat Manager
                    </a>
                </Button>
                <Button
                    size="sm"
                    class="h-8 text-xs gap-1.5 font-semibold"
                    :class="funnel.status === 'published'
                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                        : 'bg-primary text-primary-foreground hover:opacity-90'"
                    :disabled="publishing"
                    @click="publish"
                >
                    <Icon
                        v-if="publishing"
                        icon="heroicons:arrow-path"
                        class="size-3.5 animate-spin"
                    />
                    <Icon v-else icon="heroicons:rocket-launch" class="size-3.5" />
                    {{ publishing ? 'Publishing…' : funnel.status === 'published' ? 'Re-publish' : 'Publish' }}
                </Button>
            </div>
        </div>

        <!-- ── Tabs ── -->
        <Tabs v-model="activeTab" default-value="optin" class="space-y-5">
            <TabsList class="h-auto gap-0.5 p-1 bg-muted rounded-xl w-full sm:w-auto">
                <TabsTrigger value="optin" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:cursor-arrow-ripple" class="size-3.5" />
                    Opt-in Editor
                </TabsTrigger>
                <TabsTrigger value="webinar" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:video-camera" class="size-3.5" />
                    Webinar Room
                </TabsTrigger>
                <TabsTrigger value="integrations" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:puzzle-piece" class="size-3.5" />
                    Integrations
                </TabsTrigger>
                <TabsTrigger value="links" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:link" class="size-3.5" />
                    Share Links
                </TabsTrigger>
                <TabsTrigger value="chat" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-3.5" />
                    Chat
                    <span
                        v-if="conversationSummaries.length > 0"
                        class="ml-0.5 flex size-4 items-center justify-center rounded-full bg-primary text-[0.6rem] font-bold text-primary-foreground"
                    >
                        {{ conversationSummaries.length }}
                    </span>
                </TabsTrigger>
            </TabsList>

            <!-- ── Tab: Opt-in Editor ── -->
            <TabsContent value="optin" class="m-0 p-0">
                <!-- Full-height 3-pane editor workspace -->
                <div
                    class="flex flex-col overflow-hidden border shadow-sm"
                    :class="isFullscreen
                        ? 'fixed inset-0 z-50 rounded-none'
                        : 'rounded-xl'"
                    :style="isFullscreen ? '' : 'height: calc(100vh - 210px); min-height: 520px;'"
                >
                    <!-- ── Toolbar ── -->
                    <div class="flex shrink-0 items-center gap-1 border-b bg-card px-3 py-1.5">

                        <!-- Undo / Redo -->
                        <button
                            title="Undo"
                            class="flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            @click="editorUndo"
                        >
                            <Icon icon="heroicons:arrow-uturn-left" class="size-3.5" />
                        </button>
                        <button
                            title="Redo"
                            class="flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            @click="editorRedo"
                        >
                            <Icon icon="heroicons:arrow-uturn-right" class="size-3.5" />
                        </button>

                        <div class="mx-1.5 h-4 w-px bg-border" />

                        <!-- Device switcher -->
                        <button
                            title="Desktop preview"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-xs transition-colors"
                            :class="activeDevice === 'desktop' ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                            @click="setDevice('desktop')"
                        >
                            <Icon icon="heroicons:computer-desktop" class="size-3.5" />
                            Desktop
                        </button>
                        <button
                            title="Mobile preview"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-xs transition-colors"
                            :class="activeDevice === 'mobile' ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                            @click="setDevice('mobile')"
                        >
                            <Icon icon="heroicons:device-phone-mobile" class="size-3.5" />
                            Mobile
                        </button>

                        <div class="flex-1" />

                        <!-- Lock indicator -->
                        <div class="mr-1 flex items-center gap-1 text-[0.68rem] text-muted-foreground">
                            <Icon icon="heroicons:lock-closed" class="size-3 text-[#FFAD00]" />
                            Form locked
                        </div>

                        <!-- Toggle styles panel -->
                        <button
                            title="Toggle Styles panel"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-xs transition-colors"
                            :class="showStyles ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                            @click="showStyles = !showStyles"
                        >
                            <Icon icon="heroicons:paint-brush" class="size-3.5" />
                            Styles
                        </button>

                        <!-- Fullscreen toggle -->
                        <button
                            :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-xs transition-colors"
                            :class="isFullscreen ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                            @click="toggleFullscreen"
                        >
                            <Icon
                                :icon="isFullscreen ? 'heroicons:arrows-pointing-in' : 'heroicons:arrows-pointing-out'"
                                class="size-3.5"
                            />
                            {{ isFullscreen ? 'Exit' : 'Expand' }}
                        </button>

                        <div class="mx-1.5 h-4 w-px bg-border" />

                        <!-- Save button -->
                        <Button
                            size="sm"
                            class="h-7 gap-1.5 bg-primary text-xs text-primary-foreground hover:opacity-90"
                            :disabled="savingPage || pageForm.processing"
                            @click="savePage"
                        >
                            <Icon v-if="savingPage" icon="heroicons:arrow-path" class="size-3 animate-spin" />
                            <Icon v-else icon="heroicons:cloud-arrow-up" class="size-3" />
                            {{ savingPage ? 'Saving…' : 'Save Page' }}
                        </Button>
                    </div>

                    <!-- ── Main editor area ── -->
                    <div class="flex min-h-0 flex-1 overflow-hidden">

                        <!-- Left: Blocks panel -->
                        <div class="flex w-40 shrink-0 flex-col border-r bg-card">
                            <div class="shrink-0 border-b px-3 py-2">
                                <p class="text-[0.63rem] font-bold uppercase tracking-widest text-muted-foreground">Elements</p>
                                <p class="mt-0.5 text-[0.6rem] text-muted-foreground/70">Drag onto canvas</p>
                            </div>
                            <!-- GrapesJS BlockManager appended here -->
                            <div ref="blocksContainer" class="flex-1 overflow-y-auto" />
                        </div>

                        <!-- Center: Canvas -->
                        <div ref="editorContainer" class="relative min-h-0 flex-1 overflow-hidden bg-slate-100" />

                        <!-- Right: Styles panel (toggleable) -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 translate-x-3"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-to-class="opacity-0 translate-x-3"
                        >
                            <div v-show="showStyles" class="flex w-52 shrink-0 flex-col border-l bg-card">
                                <div class="flex shrink-0 items-center justify-between border-b px-3 py-2">
                                    <p class="text-[0.63rem] font-bold uppercase tracking-widest text-muted-foreground">Styles</p>
                                    <button
                                        class="flex size-5 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
                                        @click="showStyles = false"
                                    >
                                        <Icon icon="heroicons:x-mark" class="size-3" />
                                    </button>
                                </div>
                                <!-- GrapesJS StyleManager appended here -->
                                <div ref="stylesContainer" class="flex-1 overflow-y-auto" />
                            </div>
                        </Transition>
                    </div>
                </div>
            </TabsContent>

            <!-- ── Tab: Webinar Room ── -->
            <TabsContent value="webinar" class="space-y-4">
                <div class="grid gap-4 lg:grid-cols-2">

                    <!-- Room details -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-semibold">Room Details</CardTitle>
                            <CardDescription class="text-xs">Configure the webinar room content</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Webinar Title</Label>
                                <Input
                                    v-model="settingsForm.webinar_title"
                                    class="h-9 text-sm"
                                    placeholder="How to grow your business in 90 days"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Description</Label>
                                <Textarea
                                    v-model="settingsForm.webinar_description"
                                    class="h-20 resize-none text-sm"
                                    placeholder="Brief description shown above the video"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Video URL</Label>
                                <div class="relative">
                                    <Icon icon="heroicons:play-circle" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                                    <Input
                                        v-model="settingsForm.video_url"
                                        type="url"
                                        class="pl-9 h-9 text-sm"
                                        placeholder="https://www.youtube.com/embed/…"
                                    />
                                </div>
                                <p class="text-[0.65rem] text-muted-foreground">Paste a YouTube or Vimeo embed URL</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Room settings -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-semibold">Room Settings</CardTitle>
                            <CardDescription class="text-xs">Behaviour and features for attendees</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <!-- Chat mode -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Chat Mode</Label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="mode in ['simulated', 'hybrid', 'realtime']"
                                        :key="mode"
                                        class="rounded-lg border px-3 py-2.5 text-xs font-medium capitalize transition-colors"
                                        :class="settingsForm.chat_mode === mode
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-border text-muted-foreground hover:border-primary/30'"
                                        @click="settingsForm.chat_mode = mode"
                                    >
                                        <Icon
                                            :icon="mode === 'simulated' ? 'heroicons:cpu-chip' : mode === 'hybrid' ? 'heroicons:arrows-right-left' : 'heroicons:bolt'"
                                            class="mx-auto mb-1 size-4"
                                        />
                                        {{ mode }}
                                    </button>
                                </div>
                            </div>

                            <!-- Toggles -->
                            <div class="space-y-3 divide-y divide-border/60">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-foreground">Allow Replay</p>
                                        <p class="text-xs text-muted-foreground">Attendees can watch the recording after the event</p>
                                    </div>
                                    <Switch
                                        :checked="settingsForm.allow_replay"
                                        @update:checked="settingsForm.allow_replay = $event"
                                    />
                                </div>
                                <div class="flex items-center justify-between pt-3">
                                    <div>
                                        <p class="text-sm font-medium text-foreground">Double Opt-in</p>
                                        <p class="text-xs text-muted-foreground">Send a confirmation email before registering</p>
                                    </div>
                                    <Switch
                                        :checked="settingsForm.double_opt_in"
                                        @update:checked="settingsForm.double_opt_in = $event"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="flex justify-end">
                    <Button
                        size="sm"
                        class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                        :disabled="savingSettings || settingsForm.processing"
                        @click="saveSettings"
                    >
                        <Icon
                            v-if="savingSettings"
                            icon="heroicons:arrow-path"
                            class="size-3.5 animate-spin"
                        />
                        <Icon v-else icon="heroicons:check" class="size-3.5" />
                        {{ savingSettings ? 'Saving…' : 'Save settings' }}
                    </Button>
                </div>
            </TabsContent>

            <!-- ── Tab: Integrations ── -->
            <TabsContent value="integrations" class="space-y-4">
                <Card class="border shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold">ESP Integrations</CardTitle>
                        <CardDescription class="text-xs">
                            Connect an email service provider — leads will be auto-subscribed when they register.
                            <Link href="/integrations" class="text-primary underline ml-1">Add more accounts →</Link>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="integrationAccounts.length === 0" class="flex flex-col items-center py-10 gap-3 text-muted-foreground">
                            <Icon icon="heroicons:puzzle-piece" class="size-10 opacity-30" />
                            <p class="text-sm">No integration accounts yet.</p>
                            <Button as-child size="sm" variant="outline">
                                <Link href="/integrations">Connect an ESP</Link>
                            </Button>
                        </div>

                        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="account in integrationAccounts"
                                :key="account.id"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition-colors"
                                :class="settingsForm.integration_account_ids.includes(account.id)
                                    ? 'border-primary bg-primary/5'
                                    : 'hover:border-border/80'"
                            >
                                <input
                                    v-model="settingsForm.integration_account_ids"
                                    type="checkbox"
                                    class="sr-only"
                                    :value="account.id"
                                />
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                                    <Icon :icon="providerIcon(account.provider)" class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-foreground truncate">{{ account.name }}</p>
                                    <p class="text-xs text-muted-foreground capitalize">{{ account.provider }}</p>
                                </div>
                                <Icon
                                    v-if="settingsForm.integration_account_ids.includes(account.id)"
                                    icon="heroicons:check-circle"
                                    class="size-5 shrink-0 text-primary"
                                />
                                <Icon
                                    v-else
                                    icon="heroicons:plus-circle"
                                    class="size-5 shrink-0 text-muted-foreground/50"
                                />
                            </label>
                        </div>

                        <div v-if="integrationAccounts.length > 0" class="flex justify-end mt-4">
                            <Button
                                size="sm"
                                class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                                :disabled="savingSettings || settingsForm.processing"
                                @click="saveSettings"
                            >
                                <Icon icon="heroicons:check" class="size-3.5" />
                                Save integrations
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ── Tab: Share Links ── -->
            <TabsContent value="links" class="space-y-4">
                <!-- Status banner -->
                <div
                    v-if="funnel.status !== 'published'"
                    class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"
                >
                    <Icon icon="heroicons:exclamation-triangle" class="size-5 shrink-0 text-amber-600 mt-0.5" />
                    <div class="text-sm">
                        <p class="font-semibold text-amber-800">Funnel is not published yet</p>
                        <p class="text-amber-700 text-xs mt-0.5">These links won't be publicly accessible until you publish the funnel.</p>
                    </div>
                    <Button
                        size="sm"
                        class="shrink-0 ml-auto h-7 text-xs bg-amber-600 text-white hover:bg-amber-700"
                        :disabled="publishing"
                        @click="publish"
                    >
                        Publish now
                    </Button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Opt-in link -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-2">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-primary/10">
                                    <Icon icon="heroicons:cursor-arrow-ripple" class="size-4 text-primary" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-semibold">Opt-in Page</CardTitle>
                                    <CardDescription class="text-xs">Share this to collect registrations</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex rounded-lg border bg-muted/40 overflow-hidden">
                                <p class="flex-1 truncate px-3 py-2 text-xs text-muted-foreground">{{ publicLinks.optin }}</p>
                                <a
                                    :href="publicLinks.optin"
                                    target="_blank"
                                    class="flex items-center border-l px-2 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                    title="Open in new tab"
                                >
                                    <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                </a>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                class="w-full gap-1.5 text-xs h-8"
                                @click="copyLink('optin')"
                            >
                                <Icon
                                    :icon="copiedLink === 'optin' ? 'heroicons:check' : 'heroicons:clipboard-document'"
                                    class="size-3.5"
                                    :class="copiedLink === 'optin' ? 'text-emerald-600' : ''"
                                />
                                {{ copiedLink === 'optin' ? 'Copied!' : 'Copy opt-in link' }}
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Webinar link -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-2">
                                <div class="flex size-8 items-center justify-center rounded-lg" style="background:rgba(255,173,0,0.1)">
                                    <Icon icon="heroicons:video-camera" class="size-4" style="color:#FFAD00" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-semibold">Webinar Room</CardTitle>
                                    <CardDescription class="text-xs">Direct link to the webinar room</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex rounded-lg border bg-muted/40 overflow-hidden">
                                <p class="flex-1 truncate px-3 py-2 text-xs text-muted-foreground">{{ publicLinks.webinar }}</p>
                                <a
                                    :href="publicLinks.webinar"
                                    target="_blank"
                                    class="flex items-center border-l px-2 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                    title="Open in new tab"
                                >
                                    <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                </a>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                class="w-full gap-1.5 text-xs h-8"
                                @click="copyLink('webinar')"
                            >
                                <Icon
                                    :icon="copiedLink === 'webinar' ? 'heroicons:check' : 'heroicons:clipboard-document'"
                                    class="size-3.5"
                                    :class="copiedLink === 'webinar' ? 'text-emerald-600' : ''"
                                />
                                {{ copiedLink === 'webinar' ? 'Copied!' : 'Copy webinar link' }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>

            <!-- ── Tab: Chat Threads ── -->
            <TabsContent value="chat" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">Attendee Conversations</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ conversationSummaries.length }} thread{{ conversationSummaries.length !== 1 ? 's' : '' }} so far</p>
                    </div>
                    <Button as-child size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90">
                        <a :href="`/funnels/${funnel.id}/chat`">
                            <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                            Open Chat Manager
                        </a>
                    </Button>
                </div>

                <div v-if="conversationSummaries.length === 0" class="flex flex-col items-center rounded-xl border border-dashed py-14 gap-3 text-muted-foreground">
                    <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-10 opacity-30" />
                    <p class="text-sm">No conversations yet. Publish your funnel and share the webinar link.</p>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a
                        v-for="thread in conversationSummaries"
                        :key="thread.conversation_key"
                        :href="`/funnels/${funnel.id}/chat`"
                        class="flex items-start gap-3 rounded-xl border p-3.5 hover:border-primary/30 hover:bg-muted/30 transition-colors"
                    >
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <span class="text-xs font-bold text-primary">{{ thread.attendee_name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground">{{ thread.attendee_name }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ thread.attendee_email ?? 'Anonymous' }}</p>
                            <p class="text-xs text-muted-foreground truncate mt-0.5 italic">{{ thread.latest_message ?? 'No messages yet' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[0.6rem] font-medium text-muted-foreground">
                            {{ thread.message_count }}
                        </span>
                    </a>
                </div>
            </TabsContent>

        </Tabs>
    </div>
</template>
