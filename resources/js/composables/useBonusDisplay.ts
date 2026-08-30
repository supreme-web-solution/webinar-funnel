export type BonusMetaLike = Record<string, unknown> | null | undefined;

export function bonusTypeLabel(type: string): string {
    if (type === 'mini_course') return 'Mini Course';
    if (type === 'mini_app') return 'Mini App';
    if (type === 'checklist') return 'Checklist';
    return 'Ebook';
}

export function bonusCoverGradient(type: string, title: string, meta?: BonusMetaLike): string {
    if (typeof meta?.cover_gradient === 'string' && meta.cover_gradient) {
        return meta.cover_gradient;
    }

    const palettes: Record<string, string[][]> = {
        ebook: [
            ['#dc2626', '#7f1d1d'],
            ['#1e3a5f', '#0d9488'],
            ['#b45309', '#78350f'],
        ],
        mini_course: [
            ['#6366f1', '#8b5cf6'],
            ['#7c3aed', '#db2777'],
            ['#4f46e5', '#0ea5e9'],
        ],
    };

    const key = type === 'mini_course' ? 'mini_course' : 'ebook';
    const set = palettes[key] ?? palettes.ebook;
    const idx = title.split('').reduce((sum, ch) => sum + ch.charCodeAt(0), 0) % set.length;
    const [from, to] = set[idx] ?? set[0];

    return `linear-gradient(145deg, ${from} 0%, ${to} 100%)`;
}

export function bonusViewerUrl(meta?: BonusMetaLike): string | null {
    if (!meta) return null;
    const viewer = meta.viewer_url ?? meta.download_url;
    return typeof viewer === 'string' && viewer ? viewer : null;
}

export function bonusValueLabel(type: string, meta?: BonusMetaLike): string {
    if (typeof meta?.value_label === 'string' && meta.value_label) {
        return meta.value_label;
    }
    if (type === 'mini_course') return '$97 value';
    return '$27 value';
}

export function bonusDetailLine(type: string, meta?: BonusMetaLike): string {
    const pages = Array.isArray(meta?.pages) ? meta.pages.length : 0;
    const slides = Array.isArray(meta?.slides) ? meta.slides.length : 0;

    if (type === 'mini_course' && slides > 0) {
        return `${slides} lessons · swipeable course`;
    }
    if (type === 'ebook' && pages > 0) {
        return `${pages} chapters · PDF ready`;
    }
    if (typeof meta?.subtitle === 'string' && meta.subtitle) {
        return meta.subtitle;
    }

    return type === 'mini_course' ? 'Interactive slide course' : 'Professional PDF guide';
}

export function bonusOpenLabel(type: string): string {
    if (type === 'mini_course') return 'Open course';
    return 'Open PDF';
}
