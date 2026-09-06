<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Planning\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Goal */
class GoalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Goal $goal */
        $goal = $this->resource;

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'target_amount' => round((float) $goal->target_amount, 2),
            'initial_amount' => round((float) $goal->initial_amount, 2),
            'current_amount' => $goal->currentAmount(),
            'progress' => $goal->progress(),
            'deadline' => $goal->deadline?->toDateString(),
            'account_id' => $goal->account_id,
            'is_archived' => $goal->is_archived,
        ];
    }
}
