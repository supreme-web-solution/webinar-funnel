import { bonusCoverGradient } from '@/composables/useBonusDisplay';

export type ViewerSlide = {
    kind: string;
    title: string;
    subtitle?: string;
    body_html?: string;
    cover_gradient?: string;
};

type BonusLike = {
    title: string;
    bonus_type: string;
    meta?: Record<string, unknown> | null;
};

export function buildBonusViewerSlides(bonus: BonusLike): ViewerSlide[] {
    const meta = bonus.meta ?? {};
    const type = bonus.bonus_type;
    const subtitle = String(meta.subtitle ?? '');

    if (type === 'mini_course') {
        return buildCourseSlides(bonus.title, subtitle, meta);
    }

    return buildEbookSlides(bonus.title, subtitle, meta);
}

function buildEbookSlides(title: string, subtitle: string, meta: Record<string, unknown>): ViewerSlide[] {
    const pages = Array.isArray(meta.pages) ? meta.pages as Array<Record<string, unknown>> : [];
    const coverGradient = bonusCoverGradient('ebook', title, meta);

    const slides: ViewerSlide[] = [{
        kind: 'cover',
        title,
        subtitle,
        body_html: '',
        cover_gradient: coverGradient,
    }];

    if (subtitle) {
        slides.push({
            kind: 'intro',
            title: 'Introduction',
            subtitle: '',
            body_html: `<p class="viewer-lead">${escapeHtml(subtitle)}</p>`,
        });
    }

    if (pages.length > 0) {
        const outlineItems = pages
            .map((p, i) => `<li><span>${i + 1}</span> ${escapeHtml(String(p.title ?? `Chapter ${i + 1}`))}</li>`)
            .join('');
        slides.push({
            kind: 'outline',
            title: 'Book outline',
            subtitle: `${pages.length} chapters`,
            body_html: `<ul class="viewer-outline">${outlineItems}</ul>`,
        });
    }

    for (const p of pages) {
        slides.push({
            kind: 'content',
            title: String(p.title ?? 'Chapter'),
            subtitle: `Chapter ${Number(p.page ?? 0)}`,
            body_html: String(p.body_html ?? ''),
        });
    }

    return slides;
}

function buildCourseSlides(title: string, subtitle: string, meta: Record<string, unknown>): ViewerSlide[] {
    const lessons = Array.isArray(meta.slides) ? meta.slides as Array<Record<string, unknown>> : [];
    const coverGradient = bonusCoverGradient('mini_course', title, meta);

    const slides: ViewerSlide[] = [{
        kind: 'cover',
        title,
        subtitle,
        body_html: '<p class="viewer-lead">Swipe or use the arrows below to explore this mini course.</p>',
        cover_gradient: coverGradient,
    }];

    if (lessons.length > 0) {
        const outlineItems = lessons
            .map((s, i) => `<li><span>${i + 1}</span> ${escapeHtml(String(s.title ?? `Lesson ${i + 1}`))}</li>`)
            .join('');
        slides.push({
            kind: 'outline',
            title: 'Course outline',
            subtitle: `${lessons.length} modules`,
            body_html: `<ul class="viewer-outline">${outlineItems}</ul>`,
        });
    }

    for (const s of lessons) {
        let body = String(s.body_html ?? '');
        if (s.takeaway) {
            body += `<div class="viewer-takeaway"><strong>Takeaway:</strong> ${escapeHtml(String(s.takeaway))}</div>`;
        }
        slides.push({
            kind: 'lesson',
            title: String(s.title ?? 'Lesson'),
            subtitle: String(s.subtitle ?? ''),
            body_html: body,
        });
    }

    return slides;
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export function bonusDownloadUrl(meta?: Record<string, unknown> | null): string | null {
    if (!meta) return null;
    const pdf = meta.pdf_path ?? meta.download_url;
    return typeof pdf === 'string' && pdf ? pdf : null;
}
