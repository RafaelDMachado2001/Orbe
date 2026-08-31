<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Planning\Models\Goal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Goal> */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => null,
            'name' => 'Reserva de emergência',
            'target_amount' => 30000,
            'current_amount' => fake()->randomFloat(2, 0, 30000),
            'deadline' => CarbonImmutable::now()->addYear()->toDateString(),
            'is_archived' => false,
        ];
    }
}
