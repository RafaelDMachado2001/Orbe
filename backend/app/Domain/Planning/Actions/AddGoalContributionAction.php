<?php

declare(strict_types=1);

namespace App\Domain\Planning\Actions;

use App\Domain\Ledger\Actions\TransferBetweenAccountsAction;
use App\Domain\Ledger\DTOs\TransferData;
use App\Domain\Planning\DTOs\GoalContributionData;
use App\Domain\Planning\Models\Goal;
use App\Domain\Planning\Models\GoalContribution;
use Illuminate\Support\Facades\DB;

/**
 * Registra um aporte.
 *
 * Meta com conta vinculada: o aporte e sempre uma transferencia de verdade da
 * conta de origem escolhida para a conta da meta — sem meia-entrada, o
 * dinheiro se move de fato, e o saldo da conta vinculada e o extrato
 * concordam com o progresso mostrado aqui. Meta sem conta vinculada: o aporte
 * e so um registro de progresso, sem afetar saldo nenhum.
 */
final class AddGoalContributionAction
{
    public function __construct(
        private readonly TransferBetweenAccountsAction $transfer,
    ) {}

    public function handle(Goal $goal, GoalContributionData $data): GoalContribution
    {
        return DB::transaction(function () use ($goal, $data): GoalContribution {
            $transactionId = null;

            if ($goal->account_id !== null) {
                [, $incoming] = $this->transfer->handle(new TransferData(
                    fromAccountId: (int) $data->sourceAccountId,
                    toAccountId: $goal->account_id,
                    amount: $data->amount,
                    date: $data->contributedAt,
                    description: "Aporte: {$goal->name}",
                ));

                $transactionId = $incoming->id;
            }

            return GoalContribution::query()->create([
                'user_id' => $goal->user_id,
                'goal_id' => $goal->id,
                'transaction_id' => $transactionId,
                'amount' => $data->amount,
                'contributed_at' => $data->contributedAt,
            ]);
        });
    }
}
