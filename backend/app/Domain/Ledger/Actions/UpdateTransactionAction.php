<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Exceptions\TransactionNotEditableException;
use App\Domain\Ledger\Models\Transaction;

/**
 * Edita uma receita ou despesa lancada em conta.
 *
 * O tipo pode mudar (uma despesa vira receita), e a direcao acompanha — nunca
 * e informada pelo cliente. Trocar a conta e permitido: o saldo das duas se
 * ajusta sozinho, porque saldo e sempre a soma dos lancamentos, nunca um campo
 * materializado.
 */
final class UpdateTransactionAction
{
    public function handle(Transaction $transaction, TransactionData $data): Transaction
    {
        if ($transaction->isInvoicePayment()) {
            throw TransactionNotEditableException::invoicePayment();
        }

        if ($transaction->isCardPurchase()) {
            throw TransactionNotEditableException::installmentParent();
        }

        $account = Account::query()->findOrFail($data->accountId);

        $transaction->forceFill([
            'account_id' => $account->id,
            'category_id' => $data->categoryId,
            'description' => $data->description,
            'amount' => $data->amount,
            'type' => $data->type,
            'direction' => MovementDirection::forType($data->type),
            'status' => $data->status,
            'method' => $data->method,
            'competence_date' => $data->competenceDate->toDateString(),
            'paid_date' => $data->paidDate?->toDateString(),
            'notes' => $data->notes,
        ])->save();

        return $transaction->refresh();
    }
}
