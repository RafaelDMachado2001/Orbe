<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Banking\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Account */
class AccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Account $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'bank_id' => $account->bank_id,
            'nickname' => $account->nickname,
            'type' => $account->type->value,
            'type_label' => $account->type->label(),
            'initial_balance' => round((float) $account->initial_balance, 2),
            'balance' => $account->currentBalance(),
            'is_active' => $account->is_active,
        ];
    }
}
