<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Queries;

use App\Domain\Ledger\DTOs\MonthlyTotal;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Receitas e despesas realizadas por mes.
 *
 * Aqui vive a regra unica de o que conta no resultado do mes:
 *   - lancamentos de conta (receita/despesa) pela data de competencia;
 *   - parcelas de cartao pelo mes da fatura em que caem.
 *
 * A compra-mae do cartao fica de fora (is_installment_parent), senao o valor
 * total seria somado junto das parcelas. Transferencias tambem ficam de fora:
 * movimentam saldo, mas nao sao resultado.
 *
 * Duas agregacoes no banco, nenhuma soma em PHP.
 */
final class MonthlyTotalsQuery
{
    /** @return array<string, MonthlyTotal> indexado por YYYY-MM */
    public function handle(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $from->startOfMonth()->toDateString();
        $end = $to->endOfMonth()->toDateString();

        $totals = [];

        foreach ($this->accountMovements($userId, $start, $end) as $row) {
            $totals[$row->month] ??= ['income' => 0.0, 'expense' => 0.0];

            $bucket = $row->type === TransactionType::Receita->value ? 'income' : 'expense';
            $totals[$row->month][$bucket] += (float) $row->total;
        }

        foreach ($this->cardInstallments($userId, $start, $end) as $row) {
            $totals[$row->month] ??= ['income' => 0.0, 'expense' => 0.0];
            $totals[$row->month]['expense'] += (float) $row->total;
        }

        $result = [];

        foreach ($this->monthKeys($from, $to) as $month) {
            $result[$month] = new MonthlyTotal(
                month: $month,
                income: round($totals[$month]['income'] ?? 0.0, 2),
                expense: round($totals[$month]['expense'] ?? 0.0, 2),
            );
        }

        return $result;
    }

    /** @return Collection<int, \stdClass> */
    private function accountMovements(int $userId, string $start, string $end): Collection
    {
        return DB::table('transactions')
            ->selectRaw(<<<'SQL'
                TO_CHAR(competence_date, 'YYYY-MM') AS month,
                       type                         AS type,
                       SUM(amount)                  AS total
                SQL)
            ->where('user_id', $userId)
            ->where('status', '<>', TransactionStatus::Cancelado->value)
            ->where('is_installment_parent', false)
            ->whereIn('type', [TransactionType::Receita->value, TransactionType::Despesa->value])
            ->whereBetween('competence_date', [$start, $end])
            ->groupByRaw("TO_CHAR(competence_date, 'YYYY-MM'), type")
            ->get();
    }

    /** @return Collection<int, \stdClass> */
    private function cardInstallments(int $userId, string $start, string $end): Collection
    {
        return DB::table('installments')
            ->selectRaw(<<<'SQL'
                TO_CHAR(installments.competence_date, 'YYYY-MM') AS month,
                       SUM(installments.amount)                  AS total
                SQL)
            ->join('transactions', 'transactions.id', '=', 'installments.transaction_id')
            ->where('installments.user_id', $userId)
            ->where('transactions.status', '<>', TransactionStatus::Cancelado->value)
            ->whereBetween('installments.competence_date', [$start, $end])
            ->groupByRaw("TO_CHAR(installments.competence_date, 'YYYY-MM')")
            ->get();
    }

    /** @return list<string> */
    private function monthKeys(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $months = [];
        $cursor = $from->startOfMonth();
        $last = $to->startOfMonth();

        while ($cursor->lte($last)) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $months;
    }
}
