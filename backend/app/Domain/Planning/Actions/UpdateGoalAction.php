<?php

declare(strict_types=1);

namespace App\Domain\Planning\Actions;

use App\Domain\Planning\DTOs\GoalData;
use App\Domain\Planning\Models\Goal;

/** Nunca toca em `initial_amount`: depois da criacao, o progresso so anda por aporte. */
final class UpdateGoalAction
{
    public function handle(Goal $goal, GoalData $data): Goal
    {
        $goal->update([
            'name' => $data->name,
            'target_amount' => $data->targetAmount,
            'deadline' => $data->deadline,
            'account_id' => $data->accountId,
        ]);

        return $goal->refresh();
    }
}
