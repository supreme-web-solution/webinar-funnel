import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
    darkModeEnabled: ComputedRef<boolean>;
};

type AppearanceConfig = {
    darkModeEnabled?: boolean;
};

function readAppearanceConfig(): AppearanceConfig {
    if (typeof window === 'undefined') {
        return {};
    }

    return (window as Window & { __appearanceConfig?: AppearanceConfig }).__appearanceConfig ?? {};
}

export function isDarkModeEnabled(): boolean {
    return readAppearanceConfig().darkModeEnabled === true;
}

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (! isDarkModeEnabled()) {
        document.documentElement.classList.remove('dark');

        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    if (! isDarkModeEnabled()) {
        document.documentElement.classList.remove('dark');

        return;
    }

    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (! isDarkModeEnabled()) {
        document.documentElement.classList.remove('dark');
        localStorage.removeItem('appearance');
        setCookie('appearance', 'light');

        return;
    }

    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('light');

export function useAppearance(): UseAppearanceReturn {
    const darkModeEnabled = computed(() => isDarkModeEnabled());

    onMounted(() => {
        if (! isDarkModeEnabled()) {
            appearance.value = 'light';

            return;
        }

        const savedAppearance = localStorage.getItem(
            'appearance',
        ) as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (! isDarkModeEnabled()) {
            return 'light';
        }

        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        if (! isDarkModeEnabled()) {
            appearance.value = 'light';
            updateTheme('light');

            return;
        }

        appearance.value = value;

        localStorage.setItem('appearance', value);
        setCookie('appearance', value);
        updateTheme(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
        darkModeEnabled,
    };
}
