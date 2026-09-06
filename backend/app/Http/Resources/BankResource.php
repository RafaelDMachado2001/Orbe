<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Banking\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bank */
class BankResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Bank $bank */
        $bank = $this->resource;

        return [
            'id' => $bank->id,
            'name' => $bank->name,
            'slug' => $bank->slug,
            'color' => $bank->color,
            'kind' => $bank->kind->value,
            'kind_label' => $bank->kind->label(),
        ];
    }
}
