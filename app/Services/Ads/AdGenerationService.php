<?php

namespace App\Services\Ads;

use App\Models\Funnel;
use App\Models\FunnelAdCampaign;
use App\Models\FunnelAdCreative;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AdGenerationService
{
    /**
     * Run full AI research on the product and funnel, returning hooks, personas, and angles.
     *
     * @return array{hooks: list<string>, angles: list<string>, personas: list<string>, value_props: list<string>, pain_points: list<string>}
     */
    public function research(FunnelAdCampaign $campaign, Funnel $funnel): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            return $this->fallbackResearch($campaign, $funnel);
        }

        $system = <<<'SYS'
You are an expert direct-response advertising strategist. Given a product/funnel description,
you generate ad intelligence: hooks, angles, customer personas, value propositions, and pain points.
Respond ONLY with valid JSON matching the schema:
{
  "hooks": ["...", "..."],
  "angles": ["...", "..."],
  "personas": ["...", "..."],
  "value_props": ["...", "..."],
  "pain_points": ["...", "..."]
}
Each array must have exactly 5 items. hooks must be punchy 1-liners (under 12 words).
SYS;

        $goalLabel = FunnelAdCampaign::GOALS[$campaign->goal] ?? $campaign->goal;
        $user = json_encode([
            'funnel_name' => $funnel->name,
            'industry' => $campaign->industry ?? 'online business',
            'product_url' => $campaign->product_url ?? '',
            'goal' => $goalLabel,
            'goal_description' => $campaign->goal_description ?? '',
        ], JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            $content = $response->json('choices.0.message.content') ?? '';
            $data    = json_decode($content, true);

            if (is_array($data) && isset($data['hooks'])) {
                return $data;
            }
        } catch (\Throwable $e) {
            Log::warning('[Ads] AI research failed', ['error' => $e->getMessage()]);
        }

        return $this->fallbackResearch($campaign, $funnel);
    }

    /**
     * Generate copy variants (headline + primary text + description) for each hook.
     *
     * @param  list<string>  $hooks
     * @return list<array{headline: string, primary_text: string, description: string, cta_button: string}>
     */
    public function generateCopyVariants(FunnelAdCampaign $campaign, Funnel $funnel, array $hooks): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            return $this->fallbackCopyVariants($hooks);
        }

        $goalLabel = FunnelAdCampaign::GOALS[$campaign->goal] ?? $campaign->goal;
        $ctaOptions = implode('|', array_keys(FunnelAdCreative::CTA_BUTTONS));

        $system = <<<SYS
You are a world-class direct-response copywriter specialising in paid social ads.
For each hook provided, write a complete ad creative set:
- headline: max 40 chars, compelling, clear benefit
- primary_text: 80-150 words, emotional, benefit-driven, ends with CTA
- description: 25 words max, punchy sub-headline
- cta_button: one of [$ctaOptions]
Respond ONLY with valid JSON: {"variants": [ {...}, {...} ]} — one variant per hook.
SYS;

        $user = json_encode([
            'funnel_name' => $funnel->name,
            'goal' => $goalLabel,
            'product_url' => $campaign->product_url ?? '',
            'hooks' => $hooks,
        ], JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(40)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            $content  = $response->json('choices.0.message.content') ?? '';
            $data     = json_decode($content, true);
            $variants = $data['variants'] ?? null;

            if (is_array($variants) && count($variants) > 0) {
                return array_values($variants);
            }
        } catch (\Throwable $e) {
            Log::warning('[Ads] Copy variant generation failed', ['error' => $e->getMessage()]);
        }

        return $this->fallbackCopyVariants($hooks);
    }

    /**
     * Generate an ad image using gpt-image-1 and save it to storage.
     * Returns the public URL of the saved image, or null on failure.
     */
    public function generateAdImage(
        FunnelAdCampaign $campaign,
        string $hook,
        string $headline,
        string $format = 'square'
    ): ?string {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            return null;
        }

        // gpt-image-1 supports: 1024x1024, 1024x1536 (portrait), 1536x1024 (landscape), auto
        $size = match ($format) {
            'landscape' => '1536x1024',
            'story', 'reel' => '1024x1536',
            default => '1024x1024',
        };

        $prompt = "A high-quality, professional advertising banner for a digital product. "
            . "Headline text overlay in bold: \"{$headline}\". "
            . "Industry: " . ($campaign->industry ?? 'online business') . ". "
            . "Style: modern, clean, direct-response marketing, bold typography, "
            . "gradient or solid background with complementary brand colours, minimal design. "
            . "Concept: {$hook}. "
            . "No realistic human faces. No logos.";

        try {
            $response = Http::withToken($apiKey)
                ->timeout(90)
                ->post('https://api.openai.com/v1/images/generations', [
                    'model'           => 'gpt-image-1',
                    'prompt'          => $prompt,
                    'n'               => 1,
                    'size'            => $size,
                    'quality'         => 'medium',   // low|medium|high
                    'output_format'   => 'png',
                ]);

            if (! $response->successful()) {
                Log::warning('[Ads] gpt-image-1 API error', [
                    'status' => $response->status(),
                    'body'   => \Illuminate\Support\Str::limit($response->body(), 300),
                ]);
                return null;
            }

            // gpt-image-1 returns base64 in data[0].b64_json
            $b64 = $response->json('data.0.b64_json');
            if (! is_string($b64) || $b64 === '') {
                return null;
            }

            $imageData = base64_decode($b64);
            if ($imageData === false || strlen($imageData) < 100) {
                return null;
            }

            $folder   = 'ad-assets/' . now()->format('Y/m');
            $publicId = Str::uuid()->toString();

            $cloudinary = app(CloudinaryService::class);
            if ($cloudinary->isConfigured()) {
                $url = $cloudinary->uploadBinary($imageData, $folder, $publicId);
                if ($url) {
                    return $url;
                }
                Log::warning('[Ads] Cloudinary upload returned null — falling back to local storage.');
            }

            // Fallback: local public disk
            $filename = "{$folder}/{$publicId}.png";
            Storage::disk('public')->put($filename, $imageData);

            return Storage::disk('public')->url($filename);
        } catch (\Throwable $e) {
            Log::warning('[Ads] Image generation failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    // ─── Fallbacks ───────────────────────────────────────────────────────────

    /**
     * @return array{hooks: list<string>, angles: list<string>, personas: list<string>, value_props: list<string>, pain_points: list<string>}
     */
    private function fallbackResearch(FunnelAdCampaign $campaign, Funnel $funnel): array
    {
        $name = $funnel->name;

        return [
            'hooks' => [
                "Still struggling with {$campaign->industry}?",
                "The {$name} system changes everything.",
                "What if the hard part was already done?",
                "Most people never discover this shortcut.",
                "You're one funnel away from real results.",
            ],
            'angles' => [
                'Done-for-you saves time and eliminates guesswork',
                'AI-powered automation runs your business 24/7',
                'Proven system with zero learning curve',
                'Start generating results today, not next month',
                'Everything you need in one platform',
            ],
            'personas' => [
                'Beginner affiliate marketer, no tech skills, wants passive income',
                'Busy professional, limited time, needs automation',
                'Side-hustler wanting a proven system',
                'Coach or creator looking to add revenue streams',
                'Agency owner managing multiple client campaigns',
            ],
            'value_props' => [
                '51 pre-built funnels ready to launch instantly',
                'AI handles content creation, traffic, and follow-up',
                'No live webinar hosting required',
                'Built-in leads CRM from day one',
                'Runs around the clock without your involvement',
            ],
            'pain_points' => [
                'Wasting hours building funnels that never convert',
                'No traffic strategy that actually works',
                'Missing leads when you\'re not online',
                'No time to create content consistently',
                'Spending money on ads without a real system',
            ],
        ];
    }

    /**
     * @param  list<string>  $hooks
     * @return list<array{headline: string, primary_text: string, description: string, cta_button: string}>
     */
    private function fallbackCopyVariants(array $hooks): array
    {
        $variants = [];
        foreach ($hooks as $hook) {
            $variants[] = [
                'headline'     => Str::limit($hook, 40),
                'primary_text' => "{$hook}\n\nIf you've been trying to build an online business and keep running into the same walls — no time, no traffic, no content — this system was built for you.\n\nEverything is done for you. Click below to see how it works.",
                'description'  => 'Done-for-you webinar funnels powered by AI.',
                'cta_button'   => 'LEARN_MORE',
            ];
        }

        return $variants;
    }
}
