<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Cards\Actions\SyncInstallmentsAction;
use App\Domain\Ledger\Exceptions\TransactionNotEditableException;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Exclui um lancamento respeitando o que depende dele.
 *
 * Transferencia leva o par junto; compra no cartao devolve o valor as faturas
 * antes de sumir; parcelamento em conta leva as parcelas irmas, porque manter
 * "4 de 12" sozinha no extrato seria pior que apagar demais; baixa de fatura
 * nao se exclui por aqui, porque desfazer um pagamento tambem precisa reabrir
 * a fatura — isso e assunto da tela de Cartoes.
 */
final class DeleteTransactionAction
{
    public function __construct(
        private readonly SyncInstallmentsAction $syncInstallments,
    ) {}

    public function handle(Transaction $transaction): void
    {
        if ($transaction->isInvoicePayment()) {
            throw TransactionNotEditableException::invoicePayment();
        }

        if ($transaction->isCardPurchase() && $transaction->hasPaidInstallments()) {
            throw TransactionNotEditableException::paidInstallment();
        }

        DB::transaction(function () use ($transaction): void {
            if ($transaction->isCardPurchase()) {
                $this->syncInstallments->detach($transaction);
            }

            if ($transaction->isInstallmentPlan()) {
                $transaction->installmentPlan()
                    ->whereKeyNot($transaction->id)
                    ->delete();
            }

            if ($transaction->isTransfer() && $transaction->transfer_pair_id !== null) {
                $pair = Transaction::query()->find($transaction->transfer_pair_id);

                // O par aponta de volta; zerar antes evita que a FK restrinja a
                // exclusao do primeiro lado.
                $transaction->forceFill(['transfer_pair_id' => null])->save();
                $pair?->forceFill(['transfer_pair_id' => null])->save();
                $pair?->delete();
            }

            $transaction->delete();
        });
    }
}
