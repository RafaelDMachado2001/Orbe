<?php

declare(strict_types=1);

namespace App\Domain\Forecast\DTOs;

final readonly class ForecastMonth
{
    public function __construct(
        public string $month,
        public float $predictedIncome,
        public float $predictedExpense,
        public float $committedAmount,
        public float $projectedBalance,
        public int $confidence,
    ) {}

    /** Quanto sobra depois de tudo que ja esta comprometido e previsto. */
    public function leftover(): float
    {
        return round($this->predictedIncome - $this->predictedExpense, 2);
    }

    /** Percentual da renda prevista ja comprometido com fixas e parcelas. */
    public function commitmentRate(): float
    {
        return $this->predictedIncome > 0.0
            ? round(($this->committedAmount / $this->predictedIncome) * 100, 1)
            : 0.0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'month' => $this->month,
            'predicted_income' => $this->predictedIncome,
            'predicted_expense' => $this->predictedExpense,
            'committed_amount' => $this->committedAmount,
            'projected_balance' => $this->projectedBalance,
            'leftover' => $this->leftover(),
            'commitment_rate' => $this->commitmentRate(),
            'confidence' => $this->confidence,
        ];
    }
}
