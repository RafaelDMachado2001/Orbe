<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @see \App\Domain\Planning\Queries\BudgetStatusQuery */
class BudgetStatusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return $this->resource;
    }
}
