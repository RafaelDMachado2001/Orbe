<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $date = CarbonImmutable::now()->startOfMonth()->addDays(fake()->numberBetween(0, 25));

        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'credit_card_id' => null,
            'category_id' => null,
            'description' => fake()->randomElement(['Mercado', 'Farmácia', 'Uber', 'Restaurante', 'Internet']),
            'amount' => fake()->randomFloat(2, 20, 900),
            'type' => TransactionType::Despesa,
            'direction' => MovementDirection::Saida,
            'status' => TransactionStatus::Confirmado,
            'method' => PaymentMethod::Pix,
            'competence_date' => $date->toDateString(),
            'paid_date' => $date->toDateString(),
            'is_installment_parent' => false,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => TransactionType::Receita,
            'direction' => MovementDirection::Entrada,
            'description' => 'Pagamento recebido',
            'amount' => fake()->randomFloat(2, 1000, 9000),
        ]);
    }

    public function forecasted(): static
    {
        return $this->state(fn (): array => [
            'status' => TransactionStatus::Previsto,
            'paid_date' => null,
        ]);
    }
}
