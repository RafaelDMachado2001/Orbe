<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Enums\CardBrand;
use App\Domain\Cards\Models\CreditCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CreditCard> */
class CreditCardFactory extends Factory
{
    protected $model = CreditCard::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_id' => Bank::factory(),
            'payment_account_id' => null,
            'nickname' => fake()->randomElement(['Nubank Ultravioleta', 'Itaú Click', 'Inter Gold']),
            'brand' => fake()->randomElement(CardBrand::cases()),
            'last_four' => (string) fake()->numberBetween(1000, 9999),
            'limit_amount' => fake()->randomElement([5000, 8000, 12000, 20000]),
            'closing_day' => fake()->numberBetween(1, 28),
            'due_day' => fake()->numberBetween(1, 28),
            'color' => '#A07CFF',
            'is_active' => true,
        ];
    }
}
