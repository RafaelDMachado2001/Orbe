<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ForecastResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource->toArray();
    }
}
