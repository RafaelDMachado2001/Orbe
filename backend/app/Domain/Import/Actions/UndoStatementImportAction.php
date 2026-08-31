<?php

declare(strict_types=1);

namespace App\Domain\Import\Actions;

use App\Domain\Import\Exceptions\ImportNotUndoableException;
use App\Domain\Import\Models\ImportBatch;
use App\Domain\Ledger\Actions\DeleteTransactionAction;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Desfaz uma importacao inteira.
 *
 * A conferencia acontece antes de apagar qualquer coisa: e tudo ou nada. Um
 * lote apagado pela metade deixaria o usuario sem saber quais linhas ficaram,
 * e a tela de historico continuaria dizendo que a importacao existe.
 *
 * A exclusao passa pela DeleteTransactionAction para nao repetir aqui o que
 * cada tipo de lancamento exige — compra de cartao precisa devolver o valor
 * as faturas antes de sumir.
 */
final class UndoStatementImportAction
{
    public function __construct(
        private readonly DeleteTransactionAction $deleteTransaction,
    ) {}

    public function handle(ImportBatch $batch): void
    {
        /** @var Collection<int, Transaction> $transactions */
        $transactions = Transaction::query()
            ->ownedBy($batch->user_id)
            ->where('import_batch_id', $batch->id)
            ->get();

        $this->guard($transactions);

        DB::transaction(function () use ($batch, $transactions): void {
            foreach ($transactions as $transaction) {
                $this->deleteTransaction->handle($transaction);
            }

            $batch->delete();
        });
    }

    /** @param  Collection<int, Transaction>  $transactions */
    private function guard(Collection $transactions): void
    {
        foreach ($transactions as $transaction) {
            if ($transaction->isInvoicePayment()) {
                throw ImportNotUndoableException::hasInvoicePayments();
            }

            if ($transaction->isCardPurchase() && $transaction->hasPaidInstallments()) {
                throw ImportNotUndoableException::hasPaidInstallments();
            }
        }
    }
}
