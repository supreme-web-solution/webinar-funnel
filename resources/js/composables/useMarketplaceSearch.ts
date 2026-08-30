import { ref } from 'vue';
import { toast } from 'vue-sonner';

export type MarketplaceOffer = {
    title: string;
    marketplace: string;
    url: string;
    gravity_hint?: string;
    epc_hint?: string;
    why?: string;
    trend?: string;
    source?: string;
    search_keyword?: string;
    vendor?: string;
    refund_rate?: string | null;
    score?: number;
    score_label?: string;
    promote_reason?: string;
};

async function csrfToken(): Promise<string> {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function useMarketplaceSearch() {
    const searching = ref(false);
    const results = ref<MarketplaceOffer[]>([]);
    const sources = ref<string[]>([]);
    const lastError = ref<string | null>(null);

    async function search(
        keyword: string,
        options?: { endpoint?: string; marketplace?: string },
    ) {
        const q = keyword.trim();
        if (!q) return;

        searching.value = true;
        lastError.value = null;

        const endpoint = options?.endpoint ?? '/campaigns/search-offers';

        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': await csrfToken(),
                },
                body: JSON.stringify({
                    keyword: q,
                    marketplace: options?.marketplace || undefined,
                }),
            });

            const data = await res.json();
            results.value = data.results ?? [];
            sources.value = data.sources ?? [];

            if (data.error && !results.value.length) {
                lastError.value = data.error;
                toast.error(data.error);
            } else if (data.error) {
                lastError.value = data.error;
            }
        } catch {
            lastError.value = 'Product search failed — try again.';
            toast.error(lastError.value);
        } finally {
            searching.value = false;
        }
    }

    function reset() {
        results.value = [];
        sources.value = [];
        lastError.value = null;
    }

    return {
        searching,
        results,
        sources,
        lastError,
        search,
        reset,
    };
}

export function sourceLabel(source: string): string {
    if (source.includes('clickbank_apify')) return 'Live ClickBank';
    if (source.includes('jvzoo_html')) return 'JVZoo search';
    if (source.includes('warriorplus_html')) return 'WarriorPlus search';
    if (source.includes('daily_cache')) return 'Daily scan';

    return source;
}

export function campaignCreateUrl(offer: MarketplaceOffer, keyword = ''): string {
    const params = new URLSearchParams({
        mode: 'keyword',
        type: 'sales',
        keyword: offer.search_keyword ?? keyword,
        offer_url: offer.url,
        marketplace: offer.marketplace,
        title: offer.title,
    });

    return `/campaigns/create?${params.toString()}`;
}

export function campaignQuickStartPayload(offer: MarketplaceOffer, keyword = '') {
    return {
        name: offer.title,
        type: 'sales' as const,
        offer_url: offer.url,
        marketplace: offer.marketplace,
        keyword: offer.search_keyword ?? keyword,
        title: offer.title,
    };
}
