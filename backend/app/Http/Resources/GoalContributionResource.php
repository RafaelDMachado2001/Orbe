<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Planning\Models\GoalContribution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GoalContribution */
class GoalContributionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var GoalContribution $contribution */
        $contribution = $this->resource;

        return [
            'id' => $contribution->id,
            'goal_id' => $contribution->goal_id,
            'amount' => round((float) $contribution->amount, 2),
            'contributed_at' => $contribution->contributed_at->toDateString(),
            'transaction_id' => $contribution->transaction_id,
        ];
    }
}
