<?php

declare(strict_types=1);

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\Exceptions\BankNotRemovableException;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Models\CreditCard;

/**
 * Exclui um banco vazio.
 *
 * Banco com conta ou cartao fica de fora: o cascade do banco de dados levaria
 * contas, cartoes, faturas e todos os lancamentos junto, apagando meses de
 * extrato a partir de um clique em "excluir banco". A conta arquivada tambem
 * conta como uso — ela guarda historico.
 */
final class DeleteBankAction
{
    public function handle(Bank $bank): void
    {
        $accounts = $bank->accounts()->withoutUserScope()->count();
        $cards = CreditCard::query()->withoutUserScope()->where('bank_id', $bank->id)->count();

        if ($accounts > 0 || $cards > 0) {
            throw BankNotRemovableException::inUse($accounts, $cards);
        }

        $bank->delete();
    }
}
