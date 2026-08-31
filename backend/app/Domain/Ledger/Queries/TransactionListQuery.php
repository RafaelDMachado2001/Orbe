<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Queries;

use App\Domain\Ledger\DTOs\TransactionFilters;
use App\Domain\Ledger\DTOs\TransactionPage;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\MovementOrigin;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Extrato paginado da tela de Lancamentos.
 *
 * Une duas fontes que a interface enxerga como uma so lista:
 *   - lancamentos de conta (receita, despesa e os dois lados da transferencia);
 *   - parcelas de cartao, cada uma como sua propria linha, com o rotulo 3/10 e
 *     o valor da parcela — nunca o da compra inteira.
 *
 * A compra-mae fica de fora (is_installment_parent), senao apareceria em
 * dobro com as proprias parcelas. Mesma regra da MonthlyTotalsQuery.
 *
 * A parcela de uma despesa em conta nao precisa de ramo proprio: ela ja e um
 * lancamento de conta, e chega pelo primeiro ramo com o rotulo 3/12 vindo das
 * colunas da propria transacao.
 *
 * A uniao acontece no banco, dentro de um UNION ALL, e ordenacao, contagem e
 * totais rodam por cima dela. Nada e somado nem cortado em PHP: paginar em PHP
 * exigiria trazer o extrato inteiro para a memoria a cada troca de filtro.
 *
 * @phpstan-type MovementRow array{
 *     id: string,
 *     transaction_id: int,
 *     origin: string,
 *     description: string,
 *     amount: float,
 *     direction: string,
 *     type: string,
 *     status: string,
 *     method: string|null,
 *     date: string,
 *     paid_date: string|null,
 *     category: array{id: int, name: string, color: string}|null,
 *     account_id: int|null,
 *     credit_card_id: int|null,
 *     source: string,
 *     installment: string|null,
 *     installment_number: int|null,
 *     installment_total: int|null,
 *     is_recurring: bool,
 *     is_paid: bool,
 *     is_transfer: bool,
 *     is_invoice_payment: bool
 * }
 */
final class TransactionListQuery
{
    public function handle(int $userId, TransactionFilters $filters): TransactionPage
    {
        $branches = [];

        if ($filters->includesAccountMovements()) {
            $branches[] = $this->accountBranch($userId, $filters);
        }

        if ($filters->includesCardMovements()) {
            $branches[] = $this->cardBranch($userId, $filters);
        }

        if ($branches === []) {
            return TransactionPage::empty($filters->page, $filters->perPage);
        }

        $union = array_shift($branches);

        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        $movements = DB::query()->fromSub($union, 'movements');

        $summary = $this->summarize($movements->clone());

        $rows = $movements->clone()
            ->orderByDesc('competence_date')
            ->orderByDesc('transaction_id')
            ->orderByDesc('installment_id')
            ->offset($filters->offset())
            ->limit($filters->perPage)
            ->get();

        return new TransactionPage(
            rows: $rows->map(fn (object $row): array => $this->toMovement($row))->all(),
            income: $summary['income'],
            expense: $summary['expense'],
            total: $summary['entries'],
            page: $filters->page,
            perPage: $filters->perPage,
        );
    }

    /**
     * Totais do recorte inteiro, nao da pagina.
     *
     * Cancelados aparecem na lista quando o usuario pede, mas nunca somam:
     * um lancamento cancelado nao movimentou dinheiro. Transferencias tambem
     * ficam de fora — o dinheiro mudou de conta, nao de patrimonio.
     *
     * @return array{income: float, expense: float, entries: int}
     */
    private function summarize(Builder $movements): array
    {
        $row = $movements->selectRaw(<<<'SQL'
            COUNT(1) AS entries,
                   COALESCE(SUM(CASE WHEN status <> ? AND type = ? THEN amount ELSE 0 END), 0) AS income,
                   COALESCE(SUM(CASE WHEN status <> ? AND type = ? THEN amount ELSE 0 END), 0) AS expense
            SQL, [
            TransactionStatus::Cancelado->value,
            TransactionType::Receita->value,
            TransactionStatus::Cancelado->value,
            TransactionType::Despesa->value,
        ])->first();

        return [
            'income' => round((float) ($row->income ?? 0), 2),
            'expense' => round((float) ($row->expense ?? 0), 2),
            'entries' => (int) ($row->entries ?? 0),
        ];
    }

    /** Receitas, despesas e transferencias lancadas em conta. */
    private function accountBranch(int $userId, TransactionFilters $filters): Builder
    {
        $origin = MovementOrigin::Conta->value;

        $query = DB::table('transactions')
            ->selectRaw(<<<SQL
                '{$origin}'::text                     AS origin,
                       transactions.id                AS transaction_id,
                       NULL::bigint                   AS installment_id,
                       transactions.description       AS description,
                       transactions.amount            AS amount,
                       transactions.direction         AS direction,
                       transactions.type              AS type,
                       transactions.status            AS status,
                       transactions.method            AS method,
                       transactions.competence_date   AS competence_date,
                       transactions.paid_date         AS paid_date,
                       transactions.account_id        AS account_id,
                       NULL::bigint                   AS credit_card_id,
                       categories.id                  AS category_id,
                       categories.name                AS category_name,
                       categories.color               AS category_color,
                       COALESCE(accounts.nickname, banks.name) AS source_name,
                       transactions.installment_number AS installment_number,
                       transactions.installment_total  AS installment_total,
                       FALSE                          AS is_paid,
                       (transactions.recurrence_id IS NOT NULL) AS is_recurring,
                       (transactions.paid_invoice_id IS NOT NULL) AS is_invoice_payment
                SQL)
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->leftJoin('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->leftJoin('banks', 'banks.id', '=', 'accounts.bank_id')
            ->where('transactions.user_id', $userId)
            ->where('transactions.is_installment_parent', false)
            ->whereBetween('transactions.competence_date', [
                $filters->from->toDateString(),
                $filters->to->toDateString(),
            ]);

        if ($filters->types !== []) {
            $query->whereIn('transactions.type', $filters->typeValues());
        }

        if ($filters->statuses !== []) {
            $query->whereIn('transactions.status', $filters->statusValues());
        }

        if ($filters->categoryIds !== []) {
            $query->whereIn('transactions.category_id', $filters->categoryIds);
        }

        if ($filters->accountId !== null) {
            $query->where('transactions.account_id', $filters->accountId);
        }

        $this->applySearch($query, $filters);

        return $query;
    }

    /** Parcelas de cartao, uma linha por parcela, pelo mes da fatura. */
    private function cardBranch(int $userId, TransactionFilters $filters): Builder
    {
        $origin = MovementOrigin::Cartao->value;
        $direction = MovementDirection::Saida->value;

        $query = DB::table('installments')
            ->selectRaw(<<<SQL
                '{$origin}'::text                     AS origin,
                       transactions.id                AS transaction_id,
                       installments.id                AS installment_id,
                       transactions.description       AS description,
                       installments.amount            AS amount,
                       '{$direction}'::text           AS direction,
                       transactions.type              AS type,
                       transactions.status            AS status,
                       transactions.method            AS method,
                       installments.competence_date   AS competence_date,
                       NULL::date                     AS paid_date,
                       NULL::bigint                   AS account_id,
                       transactions.credit_card_id    AS credit_card_id,
                       categories.id                  AS category_id,
                       categories.name                AS category_name,
                       categories.color               AS category_color,
                       credit_cards.nickname          AS source_name,
                       installments.number            AS installment_number,
                       installments.total             AS installment_total,
                       installments.is_paid           AS is_paid,
                       FALSE                          AS is_recurring,
                       FALSE                          AS is_invoice_payment
                SQL)
            ->join('transactions', 'transactions.id', '=', 'installments.transaction_id')
            ->join('credit_cards', 'credit_cards.id', '=', 'transactions.credit_card_id')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('installments.user_id', $userId)
            ->whereBetween('installments.competence_date', [
                $filters->from->toDateString(),
                $filters->to->toDateString(),
            ]);

        if ($filters->statuses !== []) {
            $query->whereIn('transactions.status', $filters->statusValues());
        }

        if ($filters->categoryIds !== []) {
            $query->whereIn('transactions.category_id', $filters->categoryIds);
        }

        if ($filters->creditCardId !== null) {
            $query->where('transactions.credit_card_id', $filters->creditCardId);
        }

        $this->applySearch($query, $filters);

        return $query;
    }

    /**
     * Busca por descricao. Os curingas do LIKE sao escapados para que um "50%"
     * digitado pelo usuario procure o texto, nao case com tudo.
     */
    private function applySearch(Builder $query, TransactionFilters $filters): void
    {
        $search = trim((string) $filters->search);

        if ($search === '') {
            return;
        }

        $query->where(
            'transactions.description',
            'ILIKE',
            '%'.addcslashes($search, '%_\\').'%',
        );
    }

    /** @return MovementRow */
    private function toMovement(object $row): array
    {
        $isCard = $row->origin === MovementOrigin::Cartao->value;

        $hasInstallment = $row->installment_number !== null && $row->installment_total !== null;

        return [
            'id' => $isCard ? "i{$row->installment_id}" : "t{$row->transaction_id}",
            'transaction_id' => (int) $row->transaction_id,
            'origin' => (string) $row->origin,
            'description' => (string) $row->description,
            'amount' => round((float) $row->amount, 2),
            'direction' => (string) $row->direction,
            'type' => (string) $row->type,
            'status' => (string) $row->status,
            'method' => $row->method === null ? null : (string) $row->method,
            'date' => (string) $row->competence_date,
            'paid_date' => $row->paid_date === null ? null : (string) $row->paid_date,
            'category' => $this->category($row),
            'account_id' => $row->account_id === null ? null : (int) $row->account_id,
            'credit_card_id' => $row->credit_card_id === null ? null : (int) $row->credit_card_id,
            'source' => (string) ($row->source_name ?? 'Conta'),
            'installment' => $hasInstallment ? "{$row->installment_number}/{$row->installment_total}" : null,
            'installment_number' => $hasInstallment ? (int) $row->installment_number : null,
            'installment_total' => $hasInstallment ? (int) $row->installment_total : null,
            'is_recurring' => $this->boolean($row->is_recurring),
            'is_paid' => $this->boolean($row->is_paid),
            'is_transfer' => $row->type === TransactionType::Transferencia->value,
            'is_invoice_payment' => $this->boolean($row->is_invoice_payment),
        ];
    }

    /** @return array{id: int, name: string, color: string}|null */
    private function category(object $row): ?array
    {
        if ($row->category_id === null) {
            return null;
        }

        return [
            'id' => (int) $row->category_id,
            'name' => (string) $row->category_name,
            'color' => (string) ($row->category_color ?? '#4A525E'),
        ];
    }

    /**
     * O driver do Postgres devolve booleano ora como bool, ora como 't'/'f'.
     * Um (bool) cru transformaria 'f' em true.
     */
    private function boolean(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
}
