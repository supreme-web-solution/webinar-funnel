<?php

namespace Database\Factories;

use App\Models\FunnelPromotionPost;
use App\Models\FunnelPromotionScheduleEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FunnelPromotionScheduleEvent>
 */
class FunnelPromotionScheduleEventFactory extends Factory
{
    protected $model = FunnelPromotionScheduleEvent::class;

    public function definition(): array
    {
        $from = $this->faker->dateTimeBetween('-7 days', '+2 days');
        $to = (clone $from)->modify('+2 days');

        return [
            'post_id' => FunnelPromotionPost::factory(),
            'actor_id' => User::factory(),
            'from_time' => $from,
            'to_time' => $to,
            'action' => $this->faker->randomElement([
                FunnelPromotionScheduleEvent::ACTION_SCHEDULED,
                FunnelPromotionScheduleEvent::ACTION_RESCHEDULED,
            ]),
            'meta' => ['source' => 'factory'],
        ];
    }
}
