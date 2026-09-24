<?php

namespace App\Services\Traffic;

use App\Models\User;
use App\Models\UserTrafficProfile;
use App\Services\Content\PlatformFormatCatalog;
use App\Services\Promotion\PromotionPlatformCatalog;

final class UserTrafficProfileService
{
    public function __construct(
        private readonly PlatformFormatCatalog $formatCatalog,
        private readonly StandaloneTrafficWorkspaceService $workspace,
        private readonly PromotionPlatformCatalog $platformCatalog,
    ) {}

    public function getOrCreate(User $user): UserTrafficProfile
    {
        $profile = UserTrafficProfile::query()->where('user_id', $user->id)->first();

        if ($profile) {
            return $profile;
        }

        $funnel = $this->workspace->funnelForUser($user);
        $defaultFormats = $this->formatCatalog->defaultFormatsForIntensity(PlatformFormatCatalog::INTENSITY_GROWTH);
        $defaultPlatforms = $this->defaultPlatformsFromFormats($defaultFormats);

        return UserTrafficProfile::query()->create([
            'user_id' => $user->id,
            'standalone_funnel_id' => $funnel->id,
            'intensity' => PlatformFormatCatalog::INTENSITY_GROWTH,
            'enabled_platforms' => $defaultPlatforms,
            'enabled_formats' => $defaultFormats,
            'frequency_overrides' => [],
            'meta' => ['auto_select_formats' => false],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): UserTrafficProfile
    {
        $profile = $this->getOrCreate($user);

        $intensity = (string) ($data['intensity'] ?? $profile->intensity);
        $presets = $this->formatCatalog->intensityPresets();
        if (! isset($presets[$intensity])) {
            $intensity = PlatformFormatCatalog::INTENSITY_GROWTH;
        }

        $enabledFormats = $data['enabled_formats'] ?? $profile->enabled_formats;
        if (! is_array($enabledFormats) || $enabledFormats === []) {
            $enabledFormats = $this->formatCatalog->defaultFormatsForIntensity($intensity);
        }

        $validKeys = $this->formatCatalog->allFormatKeys();
        $enabledFormats = array_values(array_intersect($enabledFormats, $validKeys));

        if ($enabledFormats === []) {
            $enabledFormats = $this->formatCatalog->defaultFormatsForIntensity($intensity);
        }

        $enabledPlatforms = $data['enabled_platforms'] ?? $profile->enabled_platforms;
        if (! is_array($enabledPlatforms) || $enabledPlatforms === []) {
            $enabledPlatforms = $this->defaultPlatformsFromFormats($enabledFormats);
        }

        $catalogPlatforms = array_keys($this->formatCatalog->platforms());
        $enabledPlatforms = array_values(array_intersect($enabledPlatforms, $catalogPlatforms));

        $frequencyOverrides = $data['frequency_overrides'] ?? $profile->frequency_overrides;
        if (! is_array($frequencyOverrides)) {
            $frequencyOverrides = [];
        }

        $meta = $profile->meta ?? [];
        if (array_key_exists('auto_select_formats', $data)) {
            $meta['auto_select_formats'] = filter_var($data['auto_select_formats'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('plan_platforms', $data)) {
            $planPlatforms = is_array($data['plan_platforms']) ? $data['plan_platforms'] : [];
            $meta['plan_platforms'] = array_values(array_intersect($planPlatforms, $catalogPlatforms));
        }
        if (array_key_exists('planning_brief', $data)) {
            $meta['planning_brief'] = mb_substr(trim((string) $data['planning_brief']), 0, 2000);
        }
        if (is_array($data['meta'] ?? null)) {
            $meta = array_merge($meta, $data['meta']);
        }

        $profile->update([
            'intensity' => $intensity,
            'enabled_platforms' => $enabledPlatforms,
            'enabled_formats' => $enabledFormats,
            'frequency_overrides' => $frequencyOverrides,
            'meta' => $meta,
        ]);

        return $profile->fresh(['standaloneFunnel']);
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadForUser(User $user): array
    {
        $profile = $this->getOrCreate($user);
        $connected = $this->platformCatalog->connectedForUser((int) $user->id);

        return [
            'profile' => [
                'id' => $profile->id,
                'intensity' => $profile->intensity,
                'enabled_platforms' => $profile->enabled_platforms ?? [],
                'enabled_formats' => $profile->enabled_formats ?? [],
                'frequency_overrides' => $profile->frequency_overrides ?? [],
                'auto_select_formats' => (bool) ($profile->meta['auto_select_formats'] ?? false),
                'plan_platforms' => $this->planPlatformsForUser($user),
                'planning_brief' => (string) ($profile->meta['planning_brief'] ?? ''),
            ],
            'intensity_presets' => collect($this->formatCatalog->intensityPresets())
                ->map(fn (array $preset, string $key): array => [
                    'key' => $key,
                    'label' => (string) ($preset['label'] ?? ucfirst($key)),
                    'description' => (string) ($preset['description'] ?? ''),
                ])
                ->values()
                ->all(),
            'platforms' => collect($this->formatCatalog->platforms())
                ->map(fn (array $spec, string $key): array => [
                    'key' => $key,
                    'label' => (string) ($spec['label'] ?? ucfirst($key)),
                    'icon' => (string) ($spec['icon'] ?? ''),
                    'formats' => $this->formatCatalog->formatsForPlatform($key),
                ])
                ->values()
                ->all(),
            'catalog' => $this->formatCatalog->catalogPayload(),
            'connected_accounts' => $connected,
            'supported_platforms' => $this->platformCatalog->supportedPlatforms(),
        ];
    }

    public function frequencyForFormat(UserTrafficProfile $profile, string $formatKey): float
    {
        $overrides = $profile->frequency_overrides ?? [];
        if (isset($overrides[$formatKey]) && is_numeric($overrides[$formatKey])) {
            return (float) $overrides[$formatKey];
        }

        $spec = $this->formatCatalog->format($formatKey);
        $base = (float) ($spec['frequency_per_week'] ?? 1);

        $presets = $this->formatCatalog->intensityPresets();
        $multiplier = (float) ($presets[$profile->intensity]['frequency_multiplier'] ?? 1);

        if ($profile->intensity === PlatformFormatCatalog::INTENSITY_STARTER) {
            $multiplier = 0.75;
        }

        return max(0.25, round($base * $multiplier, 2));
    }

    /**
     * @param  list<string>  $formatKeys
     * @return list<string>
     */
    protected function defaultPlatformsFromFormats(array $formatKeys): array
    {
        $platforms = [];
        foreach ($formatKeys as $key) {
            $spec = $this->formatCatalog->format($key);
            if ($spec !== null) {
                $platforms[] = (string) ($spec['platform'] ?? '');
            }
        }

        return array_values(array_unique(array_filter($platforms)));
    }

    /**
     * Platforms Content Employee may plan for — connected accounts, optionally narrowed by user preference.
     *
     * @return list<string>
     */
    public function planPlatformsForUser(User $user): array
    {
        $connected = $this->platformCatalog->connectedPlatformKeys((int) $user->id);
        if ($connected === []) {
            return [];
        }

        $profile = $this->getOrCreate($user);
        $preferred = is_array($profile->meta['plan_platforms'] ?? null)
            ? $profile->meta['plan_platforms']
            : [];

        $preferred = array_values(array_intersect(
            array_filter($preferred, fn ($p) => is_string($p) && $p !== ''),
            $connected,
        ));

        return $preferred !== [] ? $preferred : $connected;
    }

    /**
     * @param  list<string>  $planPlatforms
     */
    public function updatePlanPlatforms(User $user, array $planPlatforms): UserTrafficProfile
    {
        return $this->update($user, ['plan_platforms' => $planPlatforms]);
    }
}
