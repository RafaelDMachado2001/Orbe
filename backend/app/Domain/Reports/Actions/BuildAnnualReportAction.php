<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Banking\Queries\AccountsOverviewQuery;
use App\Domain\Ledger\DTOs\MonthlyTotal;
use App\Domain\Ledger\Queries\CategoryBreakdownQuery;
use App\Domain\Ledger\Queries\MonthlyTotalsQuery;
use App\Domain\Reports\DTOs\AnnualReportResult;
use Carbon\CarbonImmutable;

/**
 * Fecha o ano: resultado mes a mes, ranking de categorias do ano inteiro,
 * saldo consolidado no fim de cada mes e comparacao com o ano anterior. Tudo
 * reaproveitado das queries que o dashboard e a previsao ja usam — nenhuma
 * consulta nova, so um recorte de 12 meses em vez de um so.
 */
final class BuildAnnualReportAction
{
    public function __construct(
        private readonly MonthlyTotalsQuery $monthlyTotals,
        private readonly CategoryBreakdownQuery $categoryBreakdown,
        private readonly AccountsOverviewQuery $accountsOverview,
    ) {}

    public function handle(int $userId, int $year): AnnualReportResult
    {
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->endOfDay();

        $months = array_values($this->monthlyTotals->handle($userId, $yearStart, $yearEnd));

        $totalIncome = round(array_sum(array_map(static fn (MonthlyTotal $month): float => $month->income, $months)), 2);
        $totalExpense = round(array_sum(array_map(static fn (MonthlyTotal $month): float => $month->expense, $months)), 2);

        $categoryRanking = $this->categoryBreakdown->handleBetween($userId, $yearStart, $yearEnd, 12);

        $balanceSeries = array_map(
            fn (MonthlyTotal $month): float => round(
                $this->accountsOverview->consolidatedBalanceAt(
                    $userId,
                    CarbonImmutable::createFromFormat('Y-m', $month->month)->endOfMonth(),
                ),
                2,
            ),
            $months,
        );

        $previousYearTotals = $this->monthlyTotals->handle($userId, $yearStart->subYear(), $yearEnd->subYear());
        $previousYearBalance = $previousYearTotals === []
            ? null
            : round(
                array_sum(array_map(static fn (MonthlyTotal $month): float => $month->balance(), $previousYearTotals)),
                2,
            );

        return new AnnualReportResult(
            year: $year,
            months: $months,
            totalIncome: $totalIncome,
            totalExpense: $totalExpense,
            categoryRanking: $categoryRanking,
            balanceSeries: $balanceSeries,
            previousYearBalance: $previousYearBalance,
        );
    }
}
