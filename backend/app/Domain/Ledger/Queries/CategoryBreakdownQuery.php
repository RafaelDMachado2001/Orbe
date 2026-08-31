<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Queries;

use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gastos por categoria em um mes, somando lancamentos de conta e parcelas de
 * cartao na mesma regra usada pela MonthlyTotalsQuery.
 *
 * @phpstan-type CategorySlice array{
 *     category_id: int|null,
 *     name: string,
 *     color: string,
 *     total: float,
 *     percentage: float
 * }
 */
final class CategoryBreakdownQuery
{
    /**
     * Total gasto por categoria, sem recorte nem agrupamento em "Outros".
     * Usado tambem pelo acompanhamento de orcamento.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function totalsByCategory(int $userId, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth()->toDateString();
        $end = $month->endOfMonth()->toDateString();

        $buckets = [];

        foreach ($this->accountExpenses($userId, $start, $end) as $row) {
            $this->add($buckets, $row);
        }

        foreach ($this->cardExpenses($userId, $start, $end) as $row) {
            $this->add($buckets, $row);
        }

        return $buckets;
    }

    /** @return list<CategorySlice> ordenado do maior gasto para o menor */
    public function handle(int $userId, CarbonImmutable $month, int $limit = 6): array
    {
        $buckets = $this->totalsByCategory($userId, $month);

        $grandTotal = array_sum(array_column($buckets, 'total'));

        usort($buckets, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        $top = array_slice($buckets, 0, $limit);
        $rest = array_slice($buckets, $limit);

        if ($rest !== []) {
            $top[] = [
                'category_id' => null,
                'name' => 'Outros',
                'color' => '#4A525E',
                'total' => round((float) array_sum(array_column($rest, 'total')), 2),
            ];
        }

        return array_map(
            static fn (array $slice): array => [
                ...$slice,
                'total' => round((float) $slice['total'], 2),
                'percentage' => $grandTotal > 0.0
                    ? round(((float) $slice['total'] / $grandTotal) * 100, 1)
                    : 0.0,
            ],
            $top,
        );
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $buckets
     */
    private function add(array &$buckets, \stdClass $row): void
    {
        $key = $row->category_id ?? 'sem-categoria';

        $buckets[$key] ??= [
            'category_id' => $row->category_id === null ? null : (int) $row->category_id,
            'name' => $row->name ?? 'Sem categoria',
            'color' => $row->color ?? '#4A525E',
            'total' => 0.0,
        ];

        $buckets[$key]['total'] += (float) $row->total;
    }

    /** @return Collection<int, \stdClass> */
    private function accountExpenses(int $userId, string $start, string $end): Collection
    {
        return DB::table('transactions')
            ->selectRaw(<<<'SQL'
                categories.id                    AS category_id,
                       categories.name           AS name,
                       categories.color          AS color,
                       SUM(transactions.amount)  AS total
                SQL)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.user_id', $userId)
            ->where('transactions.type', TransactionType::Despesa->value)
            ->where('transactions.status', '<>', TransactionStatus::Cancelado->value)
            ->where('transactions.is_installment_parent', false)
            ->whereBetween('transactions.competence_date', [$start, $end])
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->get();
    }

    /** @return Collection<int, \stdClass> */
    private function cardExpenses(int $userId, string $start, string $end): Collection
    {
        return DB::table('installments')
            ->selectRaw(<<<'SQL'
                categories.id                    AS category_id,
                       categories.name           AS name,
                       categories.color          AS color,
                       SUM(installments.amount)  AS total
                SQL)
            ->join('transactions', 'transactions.id', '=', 'installments.transaction_id')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('installments.user_id', $userId)
            ->where('transactions.status', '<>', TransactionStatus::Cancelado->value)
            ->whereBetween('installments.competence_date', [$start, $end])
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->get();
    }
}
