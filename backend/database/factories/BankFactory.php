<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Banking\Enums\BankKind;
use App\Domain\Banking\Models\Bank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Bank> */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->randomElement(['Nubank', 'Itaú', 'Inter', 'C6 Bank', 'Bradesco', 'XP Investimentos']);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'color' => fake()->randomElement(['#A07CFF', '#FF8A3D', '#35D68A', '#7C5CE0']),
            'kind' => fake()->randomElement(BankKind::cases()),
        ];
    }
}
