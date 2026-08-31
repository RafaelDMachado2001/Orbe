<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Cards\Enums\CardBrand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardOptionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $options */
        $options = $this->resource;

        return [
            ...$options,
            'brands' => array_map(
                static fn (CardBrand $brand): array => [
                    'value' => $brand->value,
                    'label' => $brand->label(),
                ],
                CardBrand::cases(),
            ),
        ];
    }
}
