<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Cards\Actions\SyncInstallmentsAction;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Exceptions\TransactionNotEditableException;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Confirma, volta para previsto ou cancela um lancamento em um clique.
 *
 * Existe separada da edicao porque e a acao mais frequente da tela e nao pede
 * formulario: o previsto do mes vira confirmado conforme o dinheiro sai.
 *
 * Como so o confirmado entra no saldo, a data de pagamento acompanha o status —
 * confirmar sem data deixaria o extrato sem quando.
 */
final class ChangeTransactionStatusAction
{
    public function __construct(
        private readonly SyncInstallmentsAction $syncInstallments,
    ) {}

    public function handle(Transaction $transaction, TransactionStatus $status): Transaction
    {
        if ($transaction->isInvoicePayment()) {
            throw TransactionNotEditableException::invoicePayment();
        }

        if ($transaction->isCardPurchase() && $transaction->hasPaidInstallments()) {
            throw TransactionNotEditableException::paidInstallment();
        }

        return DB::transaction(function () use ($transaction, $status): Transaction {
            $this->apply($transaction, $status);

            // A transferencia move as duas contas: os dois lados precisam
            // entrar ou sair do saldo juntos.
            if ($transaction->isTransfer() && $transaction->transfer_pair_id !== null) {
                $pair = Transaction::query()->find($transaction->transfer_pair_id);

                if ($pair !== null) {
                    $this->apply($pair, $status);
                }
            }

            // Cancelar uma compra tira as parcelas do total da fatura; reativar
            // as devolve. As parcelas em si nao mudam.
            if ($transaction->isCardPurchase()) {
                $this->syncInstallments->recalculateFor($transaction);
            }

            return $transaction->refresh();
        });
    }

    private function apply(Transaction $transaction, TransactionStatus $status): void
    {
        $transaction->forceFill([
            'status' => $status,
            'paid_date' => $status === TransactionStatus::Confirmado
                ? ($transaction->paid_date?->toDateString() ?? $transaction->competence_date->toDateString())
                : null,
        ])->save();
    }
}
