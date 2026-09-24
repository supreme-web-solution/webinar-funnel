import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

/** Shared Inertia prop from `config('app.name')` / `APP_NAME`. */
export function useAppName(): ComputedRef<string> {
    const page = usePage();

    return computed(() => String(page.props.name ?? import.meta.env.VITE_APP_NAME ?? 'App'));
}
