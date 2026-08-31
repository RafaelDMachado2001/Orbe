<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Cards\Enums\InvoiceStatus;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Cards\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $month = CarbonImmutable::now()->startOfMonth();

        return [
            'user_id' => User::factory(),
            'credit_card_id' => CreditCard::factory(),
            'reference_month' => $month->toDateString(),
            'closing_date' => $month->addDays(27)->toDateString(),
            'due_date' => $month->addMonth()->addDays(9)->toDateString(),
            'total' => 0,
            'paid_amount' => 0,
            'status' => InvoiceStatus::Aberta,
            'paid_at' => null,
        ];
    }
}
