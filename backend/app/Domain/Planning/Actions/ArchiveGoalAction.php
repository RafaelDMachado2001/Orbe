<?php

declare(strict_types=1);

namespace App\Domain\Planning\Actions;

use App\Domain\Planning\Models\Goal;

final class ArchiveGoalAction
{
    public function handle(Goal $goal, bool $isArchived): Goal
    {
        $goal->forceFill(['is_archived' => $isArchived])->save();

        return $goal->refresh();
    }
}
