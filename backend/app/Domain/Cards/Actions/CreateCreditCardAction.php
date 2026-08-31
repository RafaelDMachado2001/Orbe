<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\DTOs\CreditCardData;
use App\Domain\Cards\Models\CreditCard;

/**
 * Cadastra um cartao.
 *
 * Nenhuma fatura e criada aqui: a fatura nasce quando a primeira compra cai
 * nela (EnsureInvoiceAction). Criar doze faturas vazias so encheria a tela de
 * meses sem lancamento.
 */
final class CreateCreditCardAction
{
    public function handle(CreditCardData $data): CreditCard
    {
        $bank = Bank::query()->findOrFail($data->bankId);

        return CreditCard::query()->create([
            'user_id' => $bank->user_id,
            'bank_id' => $bank->id,
            'payment_account_id' => $data->paymentAccountId,
            'nickname' => $data->nickname,
            'brand' => $data->brand,
            'last_four' => $data->lastFour,
            'limit_amount' => $data->limitAmount,
            'closing_day' => $data->closingDay,
            'due_day' => $data->dueDay,
            'color' => $data->color,
            'is_active' => true,
        ]);
    }
}
