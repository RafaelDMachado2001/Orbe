<?php

declare(strict_types=1);

namespace App\Domain\Banking\Queries;

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\Enums\TransactionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A tela de Bancos e contas: o consolidado do topo, as contas agrupadas por
 * instituicao e a evolucao do saldo.
 *
 * Aqui o assunto e *onde o dinheiro esta*, nao o resultado do mes. Por isso o
 * movimento de cada conta soma tudo o que passou por ela, transferencia
 * inclusa: mandar dinheiro da conta corrente para a poupanca nao e despesa,
 * mas e uma saida da conta corrente — e essa e a pergunta que a tela responde.
 * Receita e despesa como resultado continuam sendo assunto da Visao geral.
 *
 * Saldo e sempre saldo inicial mais lancamentos confirmados. Nao existe coluna
 * de saldo: previsto entra na projecao, nunca no saldo de hoje.
 *
 * @phpstan-type AccountRow array{
 *     id: int,
 *     nickname: string,
 *     type: string,
 *     type_label: string,
 *     bank: array{id: int, name: string, color: string},
 *     initial_balance: float,
 *     balance: float,
 *     month_in: float,
 *     month_out: float,
 *     month_net: float,
 *     movements_count: int,
 *     last_movement_on: string|null,
 *     is_active: bool
 * }
 * @phpstan-type AccountsPage array{
 *     month: string,
 *     summary: array<string, mixed>,
 *     banks: list<array<string, mixed>>,
 *     evolution: list<array<string, mixed>>
 * }
 */
final class AccountsPageQuery
{
    /** Quantos meses a evolucao do saldo mostra, o mes navegado incluso. */
    private const EVOLUTION_MONTHS = 6;

    /**
     * O saldo consolidado nao e recalculado aqui: vem da mesma query que
     * alimenta a Visao geral. Duas telas mostrando o mesmo numero precisam
     * calcula-lo no mesmo lugar.
     */
    public function __construct(
        private readonly AccountsOverviewQuery $overview,
    ) {}

    /** @return AccountsPage */
    public function handle(int $userId, CarbonImmutable $month, bool $includeArchived = false): array
    {
        /** @var Collection<int, Account> $accounts */
        $accounts = Account::query()
            ->ownedBy($userId)
            ->with('bank')
            ->withCurrentBalance()
            ->orderBy('nickname')
            ->get();

        $flow = $this->monthFlow($userId, $month);
        $history = $this->movementHistory($userId);

        $rows = $accounts
            ->map(fn (Account $account): array => $this->row($account, $flow, $history))
            ->all();

        $visible = $includeArchived
            ? $rows
            : array_values(array_filter($rows, static fn (array $row): bool => $row['is_active']));

        return [
            'month' => $month->format('Y-m'),
            'summary' => $this->summary($rows, $userId, $month),
            'banks' => $this->groupByBank($userId, $visible),
            'evolution' => $this->evolution($userId, $month),
        ];
    }

    /**
     * @param  array<int, array{in: float, out: float}>  $flow
     * @param  array<int, array{count: int, last_on: string|null}>  $history
     * @return AccountRow
     */
    private function row(Account $account, array $flow, array $history): array
    {
        $in = round($flow[$account->id]['in'] ?? 0.0, 2);
        $out = round($flow[$account->id]['out'] ?? 0.0, 2);

        return [
            'id' => $account->id,
            'nickname' => $account->nickname,
            'type' => $account->type->value,
            'type_label' => $account->type->label(),
            'bank' => [
                'id' => $account->bank->id,
                'name' => $account->bank->name,
                'color' => $account->bank->color,
            ],
            'initial_balance' => round((float) $account->initial_balance, 2),
            'balance' => $account->currentBalance(),
            'month_in' => $in,
            'month_out' => $out,
            'month_net' => round($in - $out, 2),
            'movements_count' => $history[$account->id]['count'] ?? 0,
            'last_movement_on' => $history[$account->id]['last_on'] ?? null,
            'is_active' => $account->is_active,
        ];
    }

    /**
     * Cada instituicao com as contas dela. Bancos sem conta continuam na lista:
     * e a partir deles que a pessoa cria a primeira conta, e um banco que
     * desaparecesse por estar vazio nunca poderia ser preenchido nem excluido.
     *
     * @param  list<AccountRow>  $rows
     * @return list<array<string, mixed>>
     */
    private function groupByBank(int $userId, array $rows): array
    {
        /** @var Collection<int, Bank> $banks */
        $banks = Bank::query()
            ->ownedBy($userId)
            ->orderBy('name')
            ->get();

        $cardCounts = CreditCard::query()
            ->ownedBy($userId)
            ->selectRaw('bank_id, COUNT(1) AS total')
            ->groupBy('bank_id')
            ->pluck('total', 'bank_id');

        return $banks->map(function (Bank $bank) use ($rows, $cardCounts): array {
            $accounts = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['bank']['id'] === $bank->id,
            ));

            $active = array_filter($accounts, static fn (array $row): bool => $row['is_active']);

            return [
                'id' => $bank->id,
                'name' => $bank->name,
                'color' => $bank->color,
                'kind' => $bank->kind->value,
                'kind_label' => $bank->kind->label(),
                'balance' => round((float) array_sum(array_column($active, 'balance')), 2),
                'accounts_count' => count($accounts),
                'cards_count' => (int) ($cardCounts[$bank->id] ?? 0),
                'accounts' => $accounts,
            ];
        })->all();
    }

    /**
     * @param  list<AccountRow>  $rows
     * @return array<string, mixed>
     */
    private function summary(array $rows, int $userId, CarbonImmutable $month): array
    {
        $active = array_filter($rows, static fn (array $row): bool => $row['is_active']);

        $balance = round((float) array_sum(array_column($active, 'balance')), 2);
        $previous = $this->overview->consolidatedBalanceAt($userId, $month->startOfMonth()->subDay());

        return [
            'consolidated_balance' => $balance,
            // Sem saldo no mes anterior nao ha porcentagem: dividir por zero
            // daria "crescimento infinito" na primeira conta cadastrada.
            'delta_percent' => abs($previous) > 0.01
                ? round((($balance - $previous) / abs($previous)) * 100, 1)
                : null,
            'previous_balance' => $previous,
            'month_in' => round((float) array_sum(array_column($active, 'month_in')), 2),
            'month_out' => round((float) array_sum(array_column($active, 'month_out')), 2),
            'active_count' => count($active),
            'archived_count' => count($rows) - count($active),
            'banks_count' => Bank::query()->ownedBy($userId)->count(),
        ];
    }

    /**
     * Entradas e saidas de cada conta no mes, por competencia.
     *
     * Cancelados ficam de fora; previstos entram, porque a tela mostra o
     * movimento do mes, e um salario ainda nao confirmado ja e movimento
     * previsto daquela conta. O saldo, esse sim, so conta o confirmado.
     *
     * @return array<int, array{in: float, out: float}>
     */
    private function monthFlow(int $userId, CarbonImmutable $month): array
    {
        $rows = DB::table('transactions')
            ->selectRaw(<<<'SQL'
                account_id      AS account_id,
                       direction  AS direction,
                       SUM(amount) AS total
                SQL)
            ->where('user_id', $userId)
            ->where('status', '<>', TransactionStatus::Cancelado->value)
            ->where('is_installment_parent', false)
            ->whereNotNull('account_id')
            ->whereBetween('competence_date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->groupBy('account_id', 'direction')
            ->get();

        $flow = [];

        foreach ($rows as $row) {
            $accountId = (int) $row->account_id;
            $flow[$accountId] ??= ['in' => 0.0, 'out' => 0.0];

            $bucket = $row->direction === 'entrada' ? 'in' : 'out';
            $flow[$accountId][$bucket] += (float) $row->total;
        }

        return $flow;
    }

    /**
     * Quantos lancamentos cada conta tem e quando foi o ultimo.
     *
     * A contagem inclui cancelados de proposito: e o numero que o dialogo de
     * exclusao mostra, e um lancamento cancelado tambem seria apagado pelo
     * cascade.
     *
     * @return array<int, array{count: int, last_on: string|null}>
     */
    private function movementHistory(int $userId): array
    {
        $rows = DB::table('transactions')
            ->selectRaw(<<<'SQL'
                account_id                 AS account_id,
                       COUNT(1)             AS total,
                       MAX(competence_date) AS last_on
                SQL)
            ->where('user_id', $userId)
            ->whereNotNull('account_id')
            ->groupBy('account_id')
            ->get();

        $history = [];

        foreach ($rows as $row) {
            $history[(int) $row->account_id] = [
                'count' => (int) $row->total,
                'last_on' => $row->last_on === null ? null : (string) $row->last_on,
            ];
        }

        return $history;
    }

    /**
     * Saldo consolidado no fim de cada um dos ultimos meses.
     *
     * Parte do saldo na vespera da janela e vai somando o movimento de cada
     * mes. Chamar o saldo consolidado seis vezes daria o mesmo resultado ao
     * custo de doze consultas.
     *
     * @return list<array{month: string, label: string, balance: float}>
     */
    private function evolution(int $userId, CarbonImmutable $month): array
    {
        $last = $month->startOfMonth();
        $first = $last->subMonthsNoOverflow(self::EVOLUTION_MONTHS - 1);

        $running = $this->overview->consolidatedBalanceAt($userId, $first->subDay());
        $movements = $this->confirmedMovements($userId, $first, $last);

        $series = [];
        $cursor = $first;

        while ($cursor->lte($last)) {
            $key = $cursor->format('Y-m');
            $running = round($running + ($movements[$key] ?? 0.0), 2);

            $series[] = [
                'month' => $key,
                'label' => $cursor->translatedFormat('M'),
                'balance' => $running,
            ];

            $cursor = $cursor->addMonthNoOverflow();
        }

        return $series;
    }

    /**
     * Movimento confirmado das contas ativas na janela, somado por mes.
     *
     * @return array<string, float>
     */
    private function confirmedMovements(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('transactions')
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->selectRaw(<<<'SQL'
                TO_CHAR(transactions.competence_date, 'YYYY-MM') AS month,
                       SUM(transactions.signed_amount)           AS total
                SQL)
            ->where('transactions.user_id', $userId)
            ->where('transactions.status', TransactionStatus::Confirmado->value)
            ->where('accounts.is_active', true)
            ->whereBetween('transactions.competence_date', [
                $from->startOfMonth()->toDateString(),
                $to->endOfMonth()->toDateString(),
            ])
            ->groupByRaw("TO_CHAR(transactions.competence_date, 'YYYY-MM')")
            ->get();

        $movements = [];

        foreach ($rows as $row) {
            $movements[(string) $row->month] = (float) $row->total;
        }

        return $movements;
    }
}
