<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        return ['id' => $this->resource->id, 'category_id' => $this->resource->category_id, 'category' => ['id' => $this->resource->category->id, 'name' => $this->resource->category->name, 'color' => $this->resource->category->color], 'reference_month' => $this->resource->reference_month->format('Y-m'), 'limit_amount' => (float) $this->resource->limit_amount];
    }
}
