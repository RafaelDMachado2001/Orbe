<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Registra uma compra no cartao de credito.
 *
 * A compra e sempre gravada como uma transaction "mae" acompanhada de N
 * parcelas. As parcelas sao a unidade de relatorio (a transaction mae fica
 * fora das agregacoes mensais), o que mantem uma regra unica para compras a
 * vista e parceladas.
 *
 * A distribuicao nas faturas vive na SyncInstallmentsAction, compartilhada com
 * a edicao da compra.
 */
final class RegisterCardPurchaseAction
{
    public function __construct(
        private readonly SyncInstallmentsAction $syncInstallments,
    ) {}

    public function handle(CardPurchaseData $data): Transaction
    {
        return DB::transaction(function () use ($data): Transaction {
            $card = CreditCard::query()->findOrFail($data->creditCardId);

            $purchase = Transaction::query()->create([
                'user_id' => $card->user_id,
                'credit_card_id' => $card->id,
                'category_id' => $data->categoryId,
                'recurrence_id' => $data->recurrenceId,
                'description' => $data->description,
                'amount' => $data->amount,
                'type' => TransactionType::Despesa,
                'direction' => MovementDirection::Saida,
                'status' => $data->status,
                'method' => PaymentMethod::Credito,
                'competence_date' => $data->purchaseDate->toDateString(),
                'notes' => $data->notes,
                'is_installment_parent' => true,
            ]);

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
