<?php

declare(strict_types=1);

namespace App\Domain\Reports\DTOs;

use App\Domain\Ledger\DTOs\MonthlyTotal;

final readonly class AnnualReportResult
{
    /**
     * @param  list<MonthlyTotal>  $months  janeiro a dezembro do ano
     * @param  list<array{category_id:int|null,name:string,color:string,total:float,percentage:float}>  $categoryRanking
     * @param  list<float>  $balanceSeries  saldo consolidado no fim de cada mes
     */
    public function __construct(
        public int $year,
        public array $months,
        public float $totalIncome,
        public float $totalExpense,
        public array $categoryRanking,
        public array $balanceSeries,
        public ?float $previousYearBalance,
    ) {}

    public function balance(): float
    {
        return round($this->totalIncome - $this->totalExpense, 2);
    }

    /** Variacao do resultado do ano contra o resultado do ano anterior, em %. */
    public function yearOverYear(): ?float
    {
        if ($this->previousYearBalance === null || $this->previousYearBalance == 0.0) {
            return null;
        }

        return round((($this->balance() - $this->previousYearBalance) / abs($this->previousYearBalance)) * 100, 1);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'months' => array_map(static fn (MonthlyTotal $month): array => $month->toArray(), $this->months),
            'total_income' => $this->totalIncome,
            'total_expense' => $this->totalExpense,
            'balance' => $this->balance(),
            'balance_series' => $this->balanceSeries,
            'category_ranking' => $this->categoryRanking,
            'previous_year_balance' => $this->previousYearBalance,
            'year_over_year' => $this->yearOverYear(),
        ];
    }
}
