<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Queries;

use App\Domain\Ledger\Enums\TransactionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Lista unificada de movimentos para a interface.
 *
 * Uma parcela de cartao aparece como uma linha propria (com o rotulo 3/10 e o
 * valor da parcela, nao o da compra inteira), do lado dos lancamentos de
 * conta. As duas fontes vem em duas queries indexadas e sao intercaladas por
 * data — nunca por lazy loading linha a linha.
 *
 * @phpstan-type Movement array{
 *     id: string,
 *     description: string,
 *     amount: float,
 *     direction: string,
 *     date: string,
 *     status: string,
 *     category: array{name: string, color: string}|null,
 *     source: string,
 *     installment: string|null,
 *     is_recurring: bool
 * }
 */
final class LatestMovementsQuery
{
    /** @return list<Movement> */
    public function handle(int $userId, CarbonImmutable $month, int $limit = 6): array
    {
        $start = $month->startOfMonth()->toDateString();
        $end = $month->endOfMonth()->toDateString();

        $movements = [
            ...$this->accountMovements($userId, $start, $end, $limit),
            ...$this->cardMovements($userId, $start, $end, $limit),
        ];

        usort(
            $movements,
            static fn (array $a, array $b): int => [$b['date'], $b['id']] <=> [$a['date'], $a['id']],
        );

        return array_slice($movements, 0, $limit);
    }

    /** @return list<array<string, mixed>> */
    private function accountMovements(int $userId, string $start, string $end, int $limit): array
    {
        $rows = DB::table('transactions')
            ->selectRaw(<<<'SQL'
                transactions.id                  AS id,
                       transactions.description  AS description,
                       transactions.amount       AS amount,
                       transactions.direction    AS direction,
                       transactions.status       AS status,
                       transactions.type         AS type,
                       transactions.method       AS method,
                       transactions.competence_date AS date,
                       transactions.recurrence_id   AS recurrence_id,
                       categories.name           AS category_name,
                       categories.color          AS category_color,
                       banks.name                AS source_name
                SQL)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->leftJoin('banks', 'banks.id', '=', 'accounts.bank_id')
            ->where('transactions.user_id', $userId)
            ->where('transactions.status', '<>', TransactionStatus::Cancelado->value)
            ->where('transactions.is_installment_parent', false)
            ->whereBetween('transactions.competence_date', [$start, $end])
            ->orderByDesc('transactions.competence_date')
            ->orderByDesc('transactions.id')
            ->limit($limit)
            ->get();

        return $rows->map(fn (object $row): array => [
            'id' => "t{$row->id}",
            'description' => $row->description,
            'amount' => round((float) $row->amount, 2),
            'direction' => $row->direction,
            'date' => $row->date,
            'status' => $row->status,
            'category' => $this->category($row->category_name, $row->category_color),
            'source' => $row->source_name ?? 'Conta',
            'installment' => null,
            'is_recurring' => $row->recurrence_id !== null,
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function cardMovements(int $userId, string $start, string $end, int $limit): array
    {
        $rows = DB::table('installments')
            ->selectRaw(<<<'SQL'
                installments.id                    AS id,
                       installments.amount         AS amount,
                       installments.number         AS number,
                       installments.total          AS total,
                       installments.competence_date AS date,
                       transactions.description    AS description,
                       transactions.status         AS status,
                       categories.name             AS category_name,
                       categories.color            AS category_color,
                       credit_cards.nickname       AS source_name
                SQL)
            ->join('transactions', 'transactions.id', '=', 'installments.transaction_id')
            ->join('credit_cards', 'credit_cards.id', '=', 'transactions.credit_card_id')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('installments.user_id', $userId)
            ->where('transactions.status', '<>', TransactionStatus::Cancelado->value)
            ->whereBetween('installments.competence_date', [$start, $end])
            ->orderByDesc('installments.competence_date')
            ->orderByDesc('installments.id')
            ->limit($limit)
            ->get();

        return $rows->map(fn (object $row): array => [
            'id' => "i{$row->id}",
            'description' => $row->description,
            'amount' => round((float) $row->amount, 2),
            'direction' => 'saida',
            'date' => $row->date,
            'status' => $row->status,
            'category' => $this->category($row->category_name, $row->category_color),
            'source' => $row->source_name,
            'installment' => "{$row->number}/{$row->total}",
            'is_recurring' => false,
        ])->all();
    }

    /** @return array{name: string, color: string}|null */
    private function category(?string $name, ?string $color): ?array
    {
        if ($name === null) {
            return null;
        }

        return ['name' => $name, 'color' => $color ?? '#4A525E'];
    }
}
