<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Actions;

use App\Domain\Banking\Queries\AccountsOverviewQuery;
use App\Domain\Cards\Queries\CardsOverviewQuery;
use App\Domain\Dashboard\DTOs\DashboardData;
use App\Domain\Forecast\Actions\ForecastService;
use App\Domain\Ledger\DTOs\MonthlyTotal;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Queries\CategoryBreakdownQuery;
use App\Domain\Ledger\Queries\LatestMovementsQuery;
use App\Domain\Ledger\Queries\MonthlyTotalsQuery;
use App\Domain\Planning\Queries\BudgetStatusQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Monta a visao geral do mes em um numero fixo de queries, independente da
 * quantidade de contas, cartoes ou lancamentos.
 */
final class BuildDashboardAction
{
    /** Meses de historico exibidos no grafico, alem do mes projetado. */
    private const CHART_MONTHS = 7;

    public function __construct(
        private readonly MonthlyTotalsQuery $monthlyTotals,
        private readonly CategoryBreakdownQuery $categoryBreakdown,
        private readonly LatestMovementsQuery $latestMovements,
        private readonly AccountsOverviewQuery $accountsOverview,
        private readonly CardsOverviewQuery $cardsOverview,
        private readonly BudgetStatusQuery $budgetStatus,
        private readonly ForecastService $forecast,
    ) {}

    public function handle(int $userId, CarbonImmutable $month): DashboardData
    {
        $month = $month->startOfMonth();

        $history = $this->monthlyTotals->handle(
            $userId,
            $month->subMonthsNoOverflow(self::CHART_MONTHS - 1),
            $month,
        );

        $current = $history[$month->format('Y-m')] ?? new MonthlyTotal($month->format('Y-m'), 0.0, 0.0);

        $forecast = $this->forecast->handle($userId, horizon: 1, from: $month);
        $nextMonth = $forecast->first();

        $accounts = $this->accountsOverview->handle($userId);
        $cards = $this->cardsOverview->handle($userId, $month);
        $categories = $this->categoryBreakdown->handle($userId, $month);
        $budgets = $this->budgetStatus->handle($userId, $month);

        return new DashboardData(
            month: $month,
            consolidatedBalance: round(array_sum(array_column($accounts, 'balance')), 2),
            previousBalance: $this->accountsOverview->consolidatedBalanceAt(
                $userId,
                $month->subMonthNoOverflow()->endOfMonth(),
            ),
            income: $current->income,
            expense: $current->expense,
            incomeSources: $this->incomeSources($userId, $month),
            budgetUsage: $this->budgetStatus->overallUsage($userId, $month),
            goalProgress: $this->goalProgress($userId),
            nextMonth: $nextMonth,
            chart: $this->chart($history, $nextMonth),
            accounts: $accounts,
            cards: $cards,
            categories: $categories,
            latestMovements: $this->latestMovements->handle($userId, $month),
            budgets: $budgets,
            alerts: $this->alerts($budgets, $cards),
        );
    }

    /**
     * Serie do grafico: os meses realizados seguidos do mes projetado, que a
     * interface desenha com borda pontilhada roxa.
     *
     * @param  array<string, MonthlyTotal>  $history
     * @return list<array<string, mixed>>
     */
    private function chart(array $history, ?object $nextMonth): array
    {
        $series = array_map(
            static fn (MonthlyTotal $total): array => [
                ...$total->toArray(),
                'label' => CarbonImmutable::createFromFormat('Y-m-d', $total->month.'-01')
                    ->translatedFormat('M'),
            ],
            array_values($history),
        );

        if ($nextMonth !== null) {
            $series[] = [
                'month' => $nextMonth->month,
                'label' => CarbonImmutable::createFromFormat('Y-m-d', $nextMonth->month.'-01')
                    ->translatedFormat('M'),
                'income' => $nextMonth->predictedIncome,
                'expense' => $nextMonth->predictedExpense,
                'balance' => $nextMonth->leftover(),
                'is_forecast' => true,
            ];
        }

        return $series;
    }

    /**
     * Categorias que geraram receita no mes, para o subtitulo do KPI.
     *
     * @return list<string>
     */
    private function incomeSources(int $userId, CarbonImmutable $month): array
    {
        return DB::table('transactions')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', TransactionType::Receita->value)
            ->where('transactions.status', '<>', TransactionStatus::Cancelado->value)
            ->whereBetween('transactions.competence_date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->groupBy('categories.name')
            ->orderByRaw('SUM(transactions.amount) DESC')
            ->pluck('categories.name')
            ->filter()
            ->values()
            ->map(static fn (string $name): string => $name)
            ->all();
    }

    /**
     * Progresso agregado das metas ativas. O atual de cada meta e o inicial
     * mais a soma dos aportes — o mesmo calculo de `Goal::currentAmount()`,
     * so que em uma unica consulta para nao rodar uma por meta.
     */
    private function goalProgress(int $userId): ?float
    {
        $perGoal = DB::table('goals')
            ->leftJoin('goal_contributions', 'goal_contributions.goal_id', '=', 'goals.id')
            ->selectRaw(<<<'SQL'
                goals.id                                                        AS id,
                       MAX(goals.target_amount)                                 AS target_amount,
                       MAX(goals.initial_amount) + COALESCE(SUM(goal_contributions.amount), 0) AS current
                SQL)
            ->where('goals.user_id', $userId)
            ->where('goals.is_archived', false)
            ->groupBy('goals.id');

        $goals = DB::query()
            ->fromSub($perGoal, 'per_goal')
            ->selectRaw('SUM(target_amount) AS target, SUM(current) AS current')
            ->first();

        $target = (float) ($goals->target ?? 0);

        if ($target <= 0.0) {
            return null;
        }

        return round(min((((float) ($goals->current ?? 0)) / $target) * 100, 100), 1);
    }

    /**
     * @param  list<array<string, mixed>>  $budgets
     * @param  list<array<string, mixed>>  $cards
     * @return list<array{level: string, title: string, message: string}>
     */
    private function alerts(array $budgets, array $cards): array
    {
        $alerts = [];

        foreach ($budgets as $budget) {
            if ($budget['is_exceeded'] === true) {
                $alerts[] = [
                    'level' => 'alerta',
                    'title' => "Orçamento de {$budget['category']} estourado",
                    'message' => sprintf(
                        'Você já gastou %s%% do limite definido para este mês.',
                        number_format($budget['percentage'], 0, ',', '.'),
                    ),
                ];
            }
        }

        foreach ($cards as $card) {
            $invoice = $card['current_invoice'];

            if ($invoice === null || $invoice['days_to_close'] === null) {
                continue;
            }

            if ($invoice['days_to_close'] <= 7 && $invoice['status'] === 'aberta') {
                $alerts[] = [
                    'level' => 'atencao',
                    'title' => "Fatura do {$card['nickname']} fecha em breve",
                    'message' => sprintf(
                        'Fecha em %d dia(s) com %s lançado até agora.',
                        $invoice['days_to_close'],
                        'R$ '.number_format($invoice['total'], 2, ',', '.'),
                    ),
                ];
            }

            if ($card['usage_percent'] >= 80.0) {
                $alerts[] = [
                    'level' => 'alerta',
                    'title' => "Limite do {$card['nickname']} quase no fim",
                    'message' => sprintf('Você já usou %s%% do limite.', number_format($card['usage_percent'], 0, ',', '.')),
                ];
            }
        }

        return $alerts;
    }
}
