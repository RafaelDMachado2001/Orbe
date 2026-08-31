<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Cards\Models\Installment;
use App\Domain\Ledger\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Installment> */
class InstallmentFactory extends Factory
{
    protected $model = Installment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'transaction_id' => Transaction::factory(),
            'invoice_id' => null,
            'number' => 1,
            'total' => 1,
            'amount' => fake()->randomFloat(2, 20, 900),
            'competence_date' => CarbonImmutable::now()->startOfMonth()->toDateString(),
            'is_paid' => false,
        ];
    }
}
