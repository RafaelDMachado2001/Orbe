<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ledger\Models\Category;
use App\Domain\Planning\Models\Budget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Budget> */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'reference_month' => CarbonImmutable::now()->startOfMonth()->toDateString(),
            'limit_amount' => fake()->randomFloat(2, 300, 5000),
        ];
    }
}
