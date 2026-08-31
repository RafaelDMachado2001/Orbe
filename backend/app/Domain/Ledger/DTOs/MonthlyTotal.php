<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

final readonly class MonthlyTotal
{
    public function __construct(
        public string $month,
        public float $income,
        public float $expense,
        public bool $isForecast = false,
    ) {}

    public function balance(): float
    {
        return round($this->income - $this->expense, 2);
    }

    /** @return array{month: string, income: float, expense: float, balance: float, is_forecast: bool} */
    public function toArray(): array
    {
        return [
            'month' => $this->month,
            'income' => $this->income,
            'expense' => $this->expense,
            'balance' => $this->balance(),
            'is_forecast' => $this->isForecast,
        ];
    }
}
