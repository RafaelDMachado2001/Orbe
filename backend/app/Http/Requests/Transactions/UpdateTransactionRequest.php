<?php

declare(strict_types=1);

namespace App\Http\Requests\Transactions;

use App\Domain\Ledger\Enums\EntryKind;
use App\Domain\Ledger\Models\Transaction;

/**
 * A edicao nao aceita "kind": ele vem do proprio lancamento.
 *
 * Trocar a natureza de um registro ja gravado (uma compra parcelada virar
 * transferencia, por exemplo) mudaria as parcelas, o par espelhado e a fatura
 * de uma vez. Quem precisa disso exclui e lanca de novo.
 */
class UpdateTransactionRequest extends WriteTransactionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['prohibited'],
            ...parent::rules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'kind.prohibited' => 'A natureza do lançamento não muda na edição. Exclua e lance de novo.',
        ];
    }

    public function kind(): EntryKind
    {
        return EntryKind::forTransaction(
            $this->transaction()->type,
            $this->transaction()->isCardPurchase(),
            $this->transaction()->is_loan,
        );
    }

    public function transaction(): Transaction
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');

        return $transaction;
    }
}
