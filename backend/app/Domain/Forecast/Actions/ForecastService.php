<?php

declare(strict_types=1);

namespace App\Domain\Forecast\Actions;

use App\Domain\Banking\Queries\AccountsOverviewQuery;
use App\Domain\Forecast\DTOs\ForecastMonth;
use App\Domain\Forecast\DTOs\ForecastResult;
use App\Domain\Ledger\DTOs\MonthlyTotal;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Ledger\Queries\MonthlyTotalsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Projeta os proximos meses combinando tres fontes:
 *
 *   a) recorrencias ativas       — o que se repete e ja e conhecido;
 *   b) parcelas futuras em aberto — divida ja assumida no cartao;
 *   c) media movel de 6 meses    — o gasto variavel que sobra depois de tirar
 *                                  (a) e (b) do historico.
 *
 * O score de confianca cai com o desvio-padrao do historico (quanto mais
 * erratico o passado, menos confiavel a projecao) e com a distancia do mes
 * projetado.
 */
final class ForecastService
{
    private const HISTORY_MONTHS = 6;

    /** Confianca perdida a cada mes de distancia da projecao. */
    private const CONFIDENCE_DECAY_PER_MONTH = 6;

    public function __construct(
        private readonly MonthlyTotalsQuery $monthlyTotals,
        private readonly AccountsOverviewQuery $accountsOverview,
    ) {}

    public function handle(int $userId, int $horizon = 6, ?CarbonImmutable $from = null): ForecastResult
    {
        $horizon = max(1, min($horizon, 24));
        $reference = ($from ?? CarbonImmutable::now())->startOfMonth();

        $history = $this->monthlyTotals->handle(
            $userId,
            $reference->subMonthsNoOverflow(self::HISTORY_MONTHS),
            $reference->subMonthNoOverflow(),
        );

        $recurrences = Recurrence::query()
            ->ownedBy($userId)
            ->where('is_active', true)
            ->get();

        $installments = $this->openInstallmentsByMonth($userId, $reference, $horizon);

        $recurringIncome = $this->recurringTotal($recurrences, TransactionType::Receita, $reference);
        $recurringExpense = $this->recurringTotal($recurrences, TransactionType::Despesa, $reference);

        $variableExpense = $this->variableExpenseBaseline($history, $recurringExpense, $installments, $reference);
        $variableIncome = $this->variableIncomeBaseline($history, $recurringIncome);

        $baseConfidence = $this->baseConfidence($history);
        $balance = $this->accountsOverview->consolidatedBalance($userId);
        $runningBalance = $balance;

        $months = [];

        for ($offset = 1; $offset <= $horizon; $offset++) {
            $month = $reference->addMonthsNoOverflow($offset);
            $key = $month->format('Y-m');

            $income = round($this->recurringTotal($recurrences, TransactionType::Receita, $month) + $variableIncome, 2);
            $committed = round(
                $this->recurringTotal($recurrences, TransactionType::Despesa, $month)
                + ($installments[$key] ?? 0.0),
                2,
            );
            $expense = round($committed + $variableExpense, 2);

            $runningBalance = round($runningBalance + $income - $expense, 2);

            $months[] = new ForecastMonth(
                month: $key,
                predictedIncome: $income,
                predictedExpense: $expense,
                committedAmount: $committed,
                projectedBalance: $runningBalance,
                confidence: max(0, $baseConfidence - (($offset - 1) * self::CONFIDENCE_DECAY_PER_MONTH)),
            );
        }

        return new ForecastResult($months, $balance);
    }

    /**
     * Soma das recorrencias de um tipo que estao valendo no mes informado.
     *
     * @param  Collection<int, Recurrence>  $recurrences
     */
    private function recurringTotal(
        Collection $recurrences,
        TransactionType $type,
        CarbonImmutable $month,
    ): float {
        $total = 0.0;

        foreach ($recurrences as $recurrence) {
            if ($recurrence->type !== $type) {
                continue;
            }

            if (! $recurrence->isRunningOn($month->endOfMonth())) {
                continue;
            }

            $total += (float) $recurrence->amount
                * $recurrence->frequency->occurrencesPerMonth($recurrence->interval);
        }

        return round($total, 2);
    }

    /**
     * Parcelas ainda nao pagas que caem em cada mes do horizonte.
     *
     * @return array<string, float>
     */
    private function openInstallmentsByMonth(int $userId, CarbonImmutable $reference, int $horizon): array
    {
        return DB::table('installments')
            ->selectRaw(<<<'SQL'
                TO_CHAR(installments.competence_date, 'YYYY-MM') AS month,
                       SUM(installments.amount)                  AS total
                SQL)
            ->join('transactions', 'transactions.id', '=', 'installments.transaction_id')
            ->where('installments.user_id', $userId)
            ->where('installments.is_paid', false)
            ->where('transactions.status', '<>', 'cancelado')
            ->whereBetween('installments.competence_date', [
                $reference->addMonthNoOverflow()->startOfMonth()->toDateString(),
                $reference->addMonthsNoOverflow($horizon)->endOfMonth()->toDateString(),
            ])
            ->groupByRaw("TO_CHAR(installments.competence_date, 'YYYY-MM')")
            ->pluck('total', 'month')
            ->map(static fn (mixed $total): float => round((float) $total, 2))
            ->all();
    }

    /**
     * Gasto variavel esperado: media do historico menos a parte que ja e
     * explicada por recorrencias e parcelas. Nunca negativo.
     *
     * @param  array<string, MonthlyTotal>  $history
     * @param  array<string, float>  $installments
     */
    private function variableExpenseBaseline(
        array $history,
        float $recurringExpense,
        array $installments,
        CarbonImmutable $reference,
    ): float {
        if ($history === []) {
            return 0.0;
        }

        $average = $this->average(array_map(
            static fn (MonthlyTotal $total): float => $total->expense,
            array_values($history),
        ));

        $averageInstallments = $installments === []
            ? 0.0
            : $this->average(array_values($installments));

        return round(max($average - $recurringExpense - $averageInstallments, 0.0), 2);
    }

    /**
     * Receita variavel esperada (freelas, extras): media historica menos as
     * recorrencias de receita.
     *
     * @param  array<string, MonthlyTotal>  $history
     */
    private function variableIncomeBaseline(array $history, float $recurringIncome): float
    {
        if ($history === []) {
            return 0.0;
        }

        $average = $this->average(array_map(
            static fn (MonthlyTotal $total): float => $total->income,
            array_values($history),
        ));

        return round(max($average - $recurringIncome, 0.0), 2);
    }

    /**
     * Confianca base (0-100) derivada do coeficiente de variacao do resultado
     * mensal historico. Historico curto tambem reduz a confianca.
     *
     * @param  array<string, MonthlyTotal>  $history
     */
    private function baseConfidence(array $history): int
    {
        $balances = array_map(
            static fn (MonthlyTotal $total): float => $total->balance(),
            array_values($history),
        );

        $months = count(array_filter(
            array_values($history),
            static fn (MonthlyTotal $total): bool => $total->income > 0.0 || $total->expense > 0.0,
        ));

        if ($months < 2) {
            return 40;
        }

        $mean = $this->average($balances);
        $deviation = $this->standardDeviation($balances);

        $variation = abs($mean) > 0.01 ? abs($deviation / $mean) : 1.0;

        $score = 100 - (int) round(min($variation, 1.0) * 55);

        // Historico incompleto derruba a confianca proporcionalmente.
        $score -= (self::HISTORY_MONTHS - $months) * 4;

        return max(0, min(100, $score));
    }

    /** @param list<float> $values */
    private function average(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    /** @param list<float> $values */
    private function standardDeviation(array $values): float
    {
        $count = count($values);

        if ($count < 2) {
            return 0.0;
        }

        $mean = $this->average($values);
        $variance = array_sum(array_map(
            static fn (float $value): float => ($value - $mean) ** 2,
            $values,
        )) / $count;

        return sqrt($variance);
    }
}
