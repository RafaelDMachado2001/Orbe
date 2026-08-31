<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ledger\Models\Recurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Recurrence */
class RecurrenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Recurrence $recurrence */
        $recurrence = $this->resource;

        return [
            'id' => $recurrence->id,
            'description' => $recurrence->description,
            'amount' => round((float) $recurrence->amount, 2),
            'type' => $recurrence->type->value,
            'frequency' => $recurrence->frequency->value,
            'interval' => $recurrence->interval,
            'day_of_month' => $recurrence->day_of_month,
            'starts_on' => $recurrence->starts_on->toDateString(),
            'ends_on' => $recurrence->ends_on?->toDateString(),
            'category_id' => $recurrence->category_id,
            'account_id' => $recurrence->account_id,
            'credit_card_id' => $recurrence->credit_card_id,
            'source_kind' => $recurrence->credit_card_id === null ? 'conta' : 'cartao',
            'method' => $recurrence->method?->value,
            'is_active' => $recurrence->is_active,
        ];
    }
}
