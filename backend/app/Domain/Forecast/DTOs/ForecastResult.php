<?php

declare(strict_types=1);

namespace App\Domain\Forecast\DTOs;

final readonly class ForecastResult
{
    /** @param list<ForecastMonth> $months */
    public function __construct(
        public array $months,
        public float $currentBalance,
    ) {}

    public function first(): ?ForecastMonth
    {
        return $this->months[0] ?? null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'current_balance' => $this->currentBalance,
            'months' => array_map(
                static fn (ForecastMonth $month): array => $month->toArray(),
                $this->months,
            ),
        ];
    }
}
