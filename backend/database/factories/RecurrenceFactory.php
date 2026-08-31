<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\RecurrenceFrequency;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Recurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Recurrence> */
class RecurrenceFactory extends Factory
{
    protected $model = Recurrence::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'account_id' => Account::factory(),
            'credit_card_id' => null,
            'description' => fake()->randomElement(['Aluguel', 'Internet', 'Academia', 'Streaming']),
            'amount' => fake()->randomFloat(2, 50, 3000),
            'type' => TransactionType::Despesa,
            'method' => PaymentMethod::Boleto,
            'frequency' => RecurrenceFrequency::Mensal,
            'interval' => 1,
            'day_of_month' => fake()->numberBetween(1, 28),
            'starts_on' => CarbonImmutable::now()->subMonths(6)->startOfMonth()->toDateString(),
            'ends_on' => null,
            'last_materialized_on' => null,
            'is_active' => true,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => TransactionType::Receita,
            'description' => 'Salário',
            'amount' => fake()->randomFloat(2, 4000, 15000),
        ]);
    }
}
