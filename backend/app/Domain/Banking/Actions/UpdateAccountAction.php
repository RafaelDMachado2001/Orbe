<?php

declare(strict_types=1);

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\DTOs\AccountData;
use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;

/**
 * Edita apelido, tipo e banco de uma conta.
 *
 * O saldo inicial nao entra: mudar o ponto de partida reescreveria todos os
 * saldos ja vistos — inclusive os dos meses fechados — sem deixar rastro do
 * porque. Para bater com o extrato do banco existe o ajuste de saldo, que
 * lanca a diferenca em uma data.
 */
final class UpdateAccountAction
{
    public function handle(Account $account, AccountData $data): Account
    {
        $bank = Bank::query()->withoutUserScope()
            ->where('user_id', $account->user_id)
            ->findOrFail($data->bankId);

        $account->forceFill([
            'bank_id' => $bank->id,
            'nickname' => $data->nickname,
            'type' => $data->type,
        ])->save();

        return $account->refresh();
    }
}
