<?php

namespace Database\Factories;

use App\Models\FunnelPromotionAsset;
use App\Models\FunnelPromotionPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelPromotionAsset>
 */
class FunnelPromotionAssetFactory extends Factory
{
    protected $model = FunnelPromotionAsset::class;

    public function definition(): array
    {
        return [
            'promotion_post_id' => FunnelPromotionPost::factory(),
            'asset_type' => $this->faker->randomElement([
                FunnelPromotionAsset::TYPE_IMAGE,
                FunnelPromotionAsset::TYPE_VIDEO,
                FunnelPromotionAsset::TYPE_SCRIPT,
            ]),
            'provider' => $this->faker->randomElement(['openai_image', 'pipio']),
            'status' => FunnelPromotionAsset::STATUS_READY,
            'source_prompt' => $this->faker->sentence(12),
            'remote_id' => 'asset_'.uniqid(),
            'url' => $this->faker->imageUrl(),
            'thumbnail_url' => $this->faker->imageUrl(),
            'duration_seconds' => $this->faker->numberBetween(15, 180),
            'meta' => ['quality' => 'standard'],
        ];
    }
}
