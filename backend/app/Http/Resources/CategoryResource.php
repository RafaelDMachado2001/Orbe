<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ledger\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'color' => $this->color,
            'icon' => $this->icon,
            'is_system' => $this->is_system,
        ];
    }
}
