<?php

declare(strict_types=1);

namespace App\Domain\Planning\Actions;

use App\Domain\Ledger\Actions\DeleteTransactionAction;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Planning\Models\GoalContribution;
use Illuminate\Support\Facades\DB;

/**
 * Desfaz um aporte. Se ele veio de uma transferencia de verdade, a
 * transferencia (e o par espelhado) somem junto — reaproveita a mesma acao
 * que ja sabe desfazer transferencia na tela de Lancamentos, em vez de
 * duplicar essa logica aqui.
 */
final class DeleteGoalContributionAction
{
    public function __construct(
        private readonly DeleteTransactionAction $deleteTransaction,
    ) {}

    public function handle(GoalContribution $contribution): void
    {
        DB::transaction(function () use ($contribution): void {
            if ($contribution->transaction_id !== null) {
                $transaction = Transaction::query()->find($contribution->transaction_id);

                if ($transaction !== null) {
                    $this->deleteTransaction->handle($transaction);
                }
            }

            $contribution->delete();
        });
    }
}
