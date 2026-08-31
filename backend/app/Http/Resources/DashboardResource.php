<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Dashboard\DTOs\DashboardData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DashboardData */
class DashboardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var DashboardData $data */
        $data = $this->resource;

        return [
            'month' => $data->month->format('Y-m'),
            'month_label' => $data->month->translatedFormat('F \d\e Y'),
            'generated_at' => now()->toIso8601String(),
            'kpis' => [
                'consolidated_balance' => [
                    'value' => $data->consolidatedBalance,
                    'delta_percent' => $data->balanceDelta(),
                    'comparison_label' => 'vs. '.$data->month->subMonthNoOverflow()->translatedFormat('F'),
                ],
                'income' => [
                    'value' => $data->income,
                    'sources_count' => count($data->incomeSources),
                    'sources' => $data->incomeSources,
                ],
                'expense' => [
                    'value' => $data->expense,
                    'budget_usage_percent' => $data->budgetUsage,
                ],
                'projection' => [
                    'value' => $data->nextMonth?->projectedBalance,
                    'month' => $data->nextMonth?->month,
                    'label' => $data->nextMonth === null
                        ? null
                        : 'Projeção '.$data->month->addMonthNoOverflow()->endOfMonth()->format('d/m'),
                    'confidence' => $data->nextMonth?->confidence,
                ],
            ],
            'chart' => $data->chart,
            'accounts' => $data->accounts,
            'cards' => $data->cards,
            'categories' => $data->categories,
            'latest_movements' => $data->latestMovements,
            'budgets' => $data->budgets,
            'forecast' => [
                'committed_amount' => $data->nextMonth?->committedAmount,
                'leftover' => $data->nextMonth?->leftover(),
                'commitment_rate' => $data->nextMonth?->commitmentRate(),
                'predicted_income' => $data->nextMonth?->predictedIncome,
                'goal_progress' => $data->goalProgress,
                'confidence' => $data->nextMonth?->confidence,
                'description' => 'Baseada em recorrências, parcelas em aberto e média móvel de 6 meses.',
            ],
            'alerts' => $data->alerts,
        ];
    }
}
