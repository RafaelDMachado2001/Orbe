<?php

declare(strict_types=1);

namespace App\Domain\Banking\Queries;

use App\Domain\Banking\Models\Account;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Contas ativas com o saldo atual agregado no banco.
 *
 * @phpstan-type AccountOverview array{
 *     id: int,
 *     nickname: string,
 *     type: string,
 *     type_label: string,
 *     bank: array{id: int, name: string, color: string},
 *     balance: float
 * }
 */
final class AccountsOverviewQuery
{
    /** @return list<AccountOverview> */
    public function handle(int $userId): array
    {
        /** @var Collection<int, Account> $accounts */
        $accounts = Account::query()
            ->ownedBy($userId)
            ->with('bank')
            ->withCurrentBalance()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        return $accounts->map(fn (Account $account): array => [
            'id' => $account->id,
            'nickname' => $account->nickname,
            'type' => $account->type->value,
            'type_label' => $account->type->label(),
            'bank' => [
                'id' => $account->bank->id,
                'name' => $account->bank->name,
                'color' => $account->bank->color,
            ],
            'balance' => $account->currentBalance(),
        ])->all();
    }

    /** Saldo consolidado de todas as contas ativas. */
    public function consolidatedBalance(int $userId): float
    {
        return round(
            array_sum(array_column($this->handle($userId), 'balance')),
            2,
        );
    }

    /**
     * Saldo consolidado em uma data passada: saldo inicial das contas somado
     * aos lancamentos confirmados ate aquele dia. Usado para o comparativo
     * "vs. mes anterior" do dashboard.
     */
    public function consolidatedBalanceAt(int $userId, CarbonImmutable $date): float
    {
        $initial = Account::query()
            ->ownedBy($userId)
            ->where('is_active', true)
            ->sum('initial_balance');

        $movements = DB::table('transactions')
            ->join('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->where('transactions.user_id', $userId)
            ->where('accounts.is_active', true)
            ->where('transactions.status', 'confirmado')
            ->where('transactions.competence_date', '<=', $date->toDateString())
            ->sum('transactions.signed_amount');

        return round((float) $initial + (float) $movements, 2);
    }
}
