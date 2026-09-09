<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Planning\Queries\BudgetStatusQuery;
use Illuminate\Http\Resources\Json\JsonResource;

/** @see BudgetStatusQuery */
class BudgetStatusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return $this->resource;
    }
}
