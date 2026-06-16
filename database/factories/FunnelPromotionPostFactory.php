<?php

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelPromotionPost>
 */
class FunnelPromotionPostFactory extends Factory
{
    protected $model = FunnelPromotionPost::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([
            FunnelPromotionPost::TYPE_TEXT,
            FunnelPromotionPost::TYPE_IMAGE,
            FunnelPromotionPost::TYPE_VIDEO,
            FunnelPromotionPost::TYPE_EMAIL,
        ]);

        return [
            'user_id' => User::factory(),
            'funnel_id' => function (array $attributes): int {
                $template = Template::query()->create([
                    'name' => 'Promotion Template '.uniqid(),
                    'slug' => 'promotion-template-'.uniqid(),
                    'category' => 'marketing',
                    'conversion_style' => 'standard',
                    'is_active' => true,
                    'sort_order' => 1,
                ]);

                return Funnel::query()->create([
                    'user_id' => $attributes['user_id'],
                    'template_id' => $template->id,
                    'name' => 'Promotion Funnel',
                    'slug' => 'promotion-funnel-'.uniqid(),
                    'status' => 'draft',
                ])->id;
            },
            'title' => $this->faker->sentence(5),
            'topic' => $this->faker->sentence(3),
            'content_type' => $type,
            'platforms' => ['twitter', 'youtube'],
            'publish_mode' => FunnelPromotionPost::MODE_APPROVE_FIRST,
            'status' => FunnelPromotionPost::STATUS_DRAFT,
            'cta_url' => $this->faker->url(),
            'cta_label' => 'Learn More',
            'text_body' => $this->faker->paragraph(4),
            'email_subject' => $type === FunnelPromotionPost::TYPE_EMAIL ? $this->faker->sentence(6) : null,
            'email_body' => $type === FunnelPromotionPost::TYPE_EMAIL ? $this->faker->paragraphs(4, true) : null,
            'hashtags' => ['#marketing', '#funnel'],
            'timezone' => 'UTC',
        ];
    }
}
