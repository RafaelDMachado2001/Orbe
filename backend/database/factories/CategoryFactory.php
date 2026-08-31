<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'name' => fake()->randomElement(['Moradia', 'Alimentação', 'Transporte', 'Lazer', 'Saúde']),
            'type' => CategoryType::Despesa,
            'color' => '#A07CFF',
            'icon' => null,
            'is_system' => false,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => CategoryType::Receita,
            'name' => fake()->randomElement(['Salário', 'Serviços PJ', 'Rendimentos']),
            'color' => '#35D68A',
        ]);
    }
}
