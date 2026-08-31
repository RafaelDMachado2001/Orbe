<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Domain\Ledger\Models\Category;

class StoreCategoryRequest extends WriteCategoryRequest
{
    public function category(): ?Category
    {
        return null;
    }
}
