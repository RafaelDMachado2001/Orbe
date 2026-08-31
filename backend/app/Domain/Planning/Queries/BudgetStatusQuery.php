<?php

declare(strict_types=1);

namespace App\Domain\Planning\Queries;

use App\Domain\Ledger\Queries\CategoryBreakdownQuery;
use App\Domain\Planning\Models\Budget;
use Carbon\CarbonImmutable;

/**
 * Consumo do orcamento por categoria no mes.
 *
 * Um orcamento estourado (>= 100%) vira alerta laranja no dashboard.
 *
 * @phpstan-type BudgetStatus array{
 *     category_id: int,
 *     category: string,
 *     color: string,
 *     limit_amount: float,
 *     spent: float,
 *     percentage: float,
 *     is_exceeded: bool
 * }
 */
final class BudgetStatusQuery
{
    public function __construct(
        private readonly CategoryBreakdownQuery $categoryBreakdown,
    ) {}

    /** @return list<BudgetStatus> */
    public function handle(int $userId, CarbonImmutable $month): array
    {
        $budgets = Budget::query()
            ->ownedBy($userId)
            ->with('category')
            ->where('reference_month', $month->startOfMonth()->toDateString())
            ->get();

        if ($budgets->isEmpty()) {
            return [];
        }

        $spending = $this->categoryBreakdown->totalsByCategory($userId, $month);

        return $budgets->map(function (Budget $budget) use ($spending): array {
            $limit = (float) $budget->limit_amount;
            $spent = round((float) ($spending[$budget->category_id]['total'] ?? 0.0), 2);
            $percentage = $limit > 0.0 ? round(($spent / $limit) * 100, 1) : 0.0;

            return [
                'category_id' => $budget->category_id,
                'category' => $budget->category->name,
                'color' => $budget->category->color,
                'limit_amount' => round($limit, 2),
                'spent' => $spent,
                'percentage' => $percentage,
                'is_exceeded' => $percentage >= 100.0,
            ];
        })->all();
    }

    /** Percentual do orcamento total do mes ja consumido. */
    public function overallUsage(int $userId, CarbonImmutable $month): ?float
    {
        $statuses = $this->handle($userId, $month);

        if ($statuses === []) {
            return null;
        }

        $limit = array_sum(array_column($statuses, 'limit_amount'));
        $spent = array_sum(array_column($statuses, 'spent'));

        return $limit > 0.0 ? round(($spent / $limit) * 100, 1) : null;
    }
}
