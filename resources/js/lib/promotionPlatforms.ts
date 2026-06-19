export type PromotionPlatformMeta = { label: string; icon: string };

export const PROMOTION_PLATFORM_META: Record<string, PromotionPlatformMeta> = {
    facebook: { label: 'Facebook', icon: 'simple-icons:facebook' },
    instagram: { label: 'Instagram', icon: 'simple-icons:instagram' },
    tiktok: { label: 'TikTok', icon: 'simple-icons:tiktok' },
    linkedin: { label: 'LinkedIn', icon: 'simple-icons:linkedin' },
    pinterest: { label: 'Pinterest', icon: 'simple-icons:pinterest' },
    twitter: { label: 'X (Twitter)', icon: 'simple-icons:x' },
    youtube: { label: 'YouTube', icon: 'simple-icons:youtube' },
    reddit: { label: 'Reddit', icon: 'simple-icons:reddit' },
};

export function promotionPlatformLabel(platform: string): string {
    return PROMOTION_PLATFORM_META[platform]?.label ?? platform;
}

export function promotionPlatformIcon(platform: string): string {
    return PROMOTION_PLATFORM_META[platform]?.icon ?? 'heroicons:share';
}
