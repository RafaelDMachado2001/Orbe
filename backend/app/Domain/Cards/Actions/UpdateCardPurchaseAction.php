<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\Exceptions\TransactionNotEditableException;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Edita uma compra no cartao por inteiro.
 *
 * Mudar valor, numero de parcelas, data ou cartao redistribui as parcelas do
 * zero pela SyncInstallmentsAction e recalcula tanto as faturas que perdem
 * parcelas quanto as que recebem. Nao ha edicao de parcela isolada: a parcela
 * e consequencia da compra, nao um registro independente.
 *
 * Uma compra com parcela ja quitada junto da fatura fica travada — alterar o
 * valor faria a fatura paga divergir do que foi efetivamente pago.
 */
final class UpdateCardPurchaseAction
{
    public function __construct(
        private readonly SyncInstallmentsAction $syncInstallments,
    ) {}

    public function handle(Transaction $purchase, CardPurchaseData $data): Transaction
    {
        if (! $purchase->isCardPurchase()) {
            throw TransactionNotEditableException::installmentParent();
        }

        if ($purchase->hasPaidInstallments()) {
            throw TransactionNotEditableException::paidInstallment();
        }

        return DB::transaction(function () use ($purchase, $data): Transaction {
            $card = CreditCard::query()->findOrFail($data->creditCardId);

            $purchase->forceFill([
                'credit_card_id' => $card->id,
                'category_id' => $data->categoryId,
                'description' => $data->description,
                'amount' => $data->amount,
                'status' => $data->status,
                'competence_date' => $data->purchaseDate->toDateString(),
                'notes' => $data->notes,
            ])->save();

            $this->syncInstallments->handle(
                purchase: $purchase,
                card: $card,
                amount: $data->amount,
                purchaseDate: $data->purchaseDate,
                installments: $data->installments,
            );

            return $purchase->refresh();
        });
    }
}
