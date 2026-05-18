const LOADER_ID = 'public-page-transition-loader';
const STYLES_ID = 'public-page-transition-loader-styles';

const ensureLoaderStyles = (): void => {
    if (document.getElementById(STYLES_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = STYLES_ID;
    style.textContent = `
        @keyframes public-page-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        #${LOADER_ID}[hidden] {
            display: none !important;
        }
        #${LOADER_ID} .public-page-shimmer {
            background: linear-gradient(90deg, #e2e8f0 25%, #f8fafc 50%, #e2e8f0 75%);
            background-size: 200% 100%;
            animation: public-page-shimmer 1.2s ease-in-out infinite;
        }
    `;
    document.head.appendChild(style);
};

const buildLoader = (label: string): HTMLElement => {
    const root = document.createElement('div');
    root.id = LOADER_ID;
    root.setAttribute('aria-busy', 'true');
    root.setAttribute('aria-live', 'polite');
    root.style.cssText =
        'position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.25rem;background:#f1f5f9;padding:1.5rem;';

    const labelEl = document.createElement('p');
    labelEl.dataset.loaderLabel = '';
    labelEl.textContent = label;
    labelEl.style.cssText = 'margin:0;font-size:0.875rem;font-weight:600;color:#64748b;';

    const stack = document.createElement('div');
    stack.style.cssText =
        'width:min(28rem,100%);display:flex;flex-direction:column;gap:0.75rem;';

    const bars: Array<{ height: string; width: string; delay?: string; marginTop?: string }> = [
        { height: '2.5rem', width: '75%' },
        { height: '1rem', width: '100%', delay: '0.1s' },
        { height: '1rem', width: '88%', delay: '0.2s' },
        { height: '2.75rem', width: '100%', delay: '0.15s', marginTop: '0.5rem' },
    ];

    for (const bar of bars) {
        const el = document.createElement('div');
        el.className = 'public-page-shimmer';
        el.style.height = bar.height;
        el.style.width = bar.width;
        el.style.borderRadius = bar.height === '2.5rem' || bar.height === '2.75rem' ? '0.5rem' : '0.375rem';
        if (bar.delay) {
            el.style.animationDelay = bar.delay;
        }
        if (bar.marginTop) {
            el.style.marginTop = bar.marginTop;
        }
        stack.appendChild(el);
    }

    root.append(labelEl, stack);

    return root;
};

export const showPublicPageTransitionLoader = (label = 'Loading…'): void => {
    ensureLoaderStyles();

    let loader = document.getElementById(LOADER_ID);

    if (!loader) {
        loader = buildLoader(label);
        document.body.appendChild(loader);
    } else {
        loader.removeAttribute('hidden');
        const labelEl = loader.querySelector('[data-loader-label]');
        if (labelEl) {
            labelEl.textContent = label;
        }
    }
};

export const hidePublicPageTransitionLoader = (): void => {
    document.getElementById(LOADER_ID)?.setAttribute('hidden', '');
};

export const waitForPaint = (): Promise<void> =>
    new Promise((resolve) => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => resolve());
        });
    });
