<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Planning\Models\Goal;
use App\Domain\Planning\Models\GoalContribution;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GoalContribution> */
class GoalContributionFactory extends Factory
{
    protected $model = GoalContribution::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'goal_id' => Goal::factory(),
            'transaction_id' => null,
            'amount' => fake()->randomFloat(2, 50, 1000),
            'contributed_at' => CarbonImmutable::now()->toDateString(),
        ];
    }
}
