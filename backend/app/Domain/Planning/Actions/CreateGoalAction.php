<?php

declare(strict_types=1);

namespace App\Domain\Planning\Actions;

use App\Domain\Planning\DTOs\GoalData;
use App\Domain\Planning\Models\Goal;
use App\Models\User;

final class CreateGoalAction
{
    public function handle(User $user, GoalData $data): Goal
    {
        return Goal::query()->create([
            'user_id' => $user->id,
            'name' => $data->name,
            'target_amount' => $data->targetAmount,
            'initial_amount' => $data->initialAmount ?? '0',
            'deadline' => $data->deadline,
            'account_id' => $data->accountId,
        ]);
    }
}
