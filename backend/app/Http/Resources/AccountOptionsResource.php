<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Banking\Enums\AccountType;
use App\Domain\Banking\Enums\BankKind;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountOptionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $options */
        $options = $this->resource;

        return [
            ...$options,
            'account_types' => array_map(
                static fn (AccountType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                ],
                AccountType::cases(),
            ),
            'bank_kinds' => array_map(
                static fn (BankKind $kind): array => [
                    'value' => $kind->value,
                    'label' => $kind->label(),
                ],
                BankKind::cases(),
            ),
        ];
    }
}
