<?php

namespace Database\Factories;

use App\Models\Funnel;
use App\Models\FunnelPromotionTopicSuggestion;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelPromotionTopicSuggestion>
 */
class FunnelPromotionTopicSuggestionFactory extends Factory
{
    protected $model = FunnelPromotionTopicSuggestion::class;

    public function definition(): array
    {
        return [
            'funnel_id' => function (): int {
                $user = User::factory()->create();
                $template = Template::query()->create([
                    'name' => 'Topic Template '.uniqid(),
                    'slug' => 'topic-template-'.uniqid(),
                    'category' => 'marketing',
                    'conversion_style' => 'standard',
                    'is_active' => true,
                    'sort_order' => 1,
                ]);

                return Funnel::query()->create([
                    'user_id' => $user->id,
                    'template_id' => $template->id,
                    'name' => 'Topic Funnel',
                    'slug' => 'topic-funnel-'.uniqid(),
                    'status' => 'draft',
                ])->id;
            },
            'topic' => $this->faker->sentence(6),
            'angle' => $this->faker->randomElement(['problem', 'proof', 'how-to']),
            'status' => FunnelPromotionTopicSuggestion::STATUS_SUGGESTED,
            'score' => $this->faker->numberBetween(30, 99),
        ];
    }
}
