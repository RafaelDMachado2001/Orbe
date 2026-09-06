<?php

declare(strict_types=1);

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\DTOs\AccountData;
use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;

/**
 * Cadastra uma conta dentro de um banco.
 *
 * O saldo inicial informado aqui e o unico saldo que a pessoa digita: dali em
 * diante o saldo e sempre saldo inicial mais lancamentos confirmados, nunca um
 * numero guardado em coluna.
 */
final class CreateAccountAction
{
    public function handle(AccountData $data): Account
    {
        $bank = Bank::query()->findOrFail($data->bankId);

        return Account::query()->create([
            'user_id' => $bank->user_id,
            'bank_id' => $bank->id,
            'nickname' => $data->nickname,
            'type' => $data->type,
            'initial_balance' => $data->initialBalance ?? 0.0,
            'is_active' => true,
        ]);
    }
}
