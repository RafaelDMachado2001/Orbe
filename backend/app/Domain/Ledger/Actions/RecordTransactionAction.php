<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Banking\Models\Account;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Models\Transaction;

/**
 * Registra uma receita ou despesa em conta (pix, debito, boleto, dinheiro).
 *
 * Lancamentos previstos entram na projecao, mas nao alteram o saldo atual —
 * essa distincao vive no status, nunca em um campo de saldo materializado.
 */
final class RecordTransactionAction
{
    public function handle(TransactionData $data): Transaction
    {
        $account = Account::query()->findOrFail($data->accountId);

        return Transaction::query()->create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'category_id' => $data->categoryId,
            'recurrence_id' => $data->recurrenceId,
            'description' => $data->description,
            'amount' => $data->amount,
            'type' => $data->type,
            'direction' => MovementDirection::forType($data->type),
            'status' => $data->status,
            'method' => $data->method,
            'competence_date' => $data->competenceDate->toDateString(),
            'paid_date' => $data->paidDate?->toDateString(),
            'notes' => $data->notes,
            'is_installment_parent' => false,
        ]);
    }
}
