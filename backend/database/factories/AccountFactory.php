<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Banking\Enums\AccountType;
use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_id' => Bank::factory(),
            'nickname' => fake()->randomElement(['Conta principal', 'Reserva', 'Conta PJ', 'Carteira']),
            'type' => AccountType::Corrente,
            'initial_balance' => fake()->randomFloat(2, 0, 10000),
            'is_active' => true,
        ];
    }
}
