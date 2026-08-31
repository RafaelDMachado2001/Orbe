<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\DTOs;

use App\Domain\Forecast\DTOs\ForecastMonth;
use Carbon\CarbonImmutable;

/**
 * Retrato completo do mes exibido na visao geral.
 *
 * @phpstan-type Alert array{level: string, title: string, message: string}
 */
final readonly class DashboardData
{
    /**
     * @param  list<array<string, mixed>>  $chart
     * @param  list<array<string, mixed>>  $accounts
     * @param  list<array<string, mixed>>  $cards
     * @param  list<array<string, mixed>>  $categories
     * @param  list<array<string, mixed>>  $latestMovements
     * @param  list<array<string, mixed>>  $budgets
     * @param  list<Alert>  $alerts
     * @param  list<string>  $incomeSources
     */
    public function __construct(
        public CarbonImmutable $month,
        public float $consolidatedBalance,
        public float $previousBalance,
        public float $income,
        public float $expense,
        public array $incomeSources,
        public ?float $budgetUsage,
        public ?float $goalProgress,
        public ?ForecastMonth $nextMonth,
        public array $chart,
        public array $accounts,
        public array $cards,
        public array $categories,
        public array $latestMovements,
        public array $budgets,
        public array $alerts,
    ) {}

    /** Variacao percentual do saldo consolidado contra o mes anterior. */
    public function balanceDelta(): ?float
    {
        if (abs($this->previousBalance) < 0.01) {
            return null;
        }

        return round(
            (($this->consolidatedBalance - $this->previousBalance) / abs($this->previousBalance)) * 100,
            1,
        );
    }
}
