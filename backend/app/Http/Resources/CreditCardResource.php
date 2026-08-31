<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Cards\Models\CreditCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditCard */
class CreditCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CreditCard $card */
        $card = $this->resource;

        return [
            'id' => $card->id,
            'bank_id' => $card->bank_id,
            'payment_account_id' => $card->payment_account_id,
            'nickname' => $card->nickname,
            'brand' => $card->brand->value,
            'brand_label' => $card->brand->label(),
            'last_four' => $card->last_four,
            'limit_amount' => round((float) $card->limit_amount, 2),
            'closing_day' => $card->closing_day,
            'due_day' => $card->due_day,
            'color' => $card->color,
            'is_active' => $card->is_active,
        ];
    }
}
