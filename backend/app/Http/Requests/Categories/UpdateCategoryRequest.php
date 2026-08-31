<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Domain\Ledger\Models\Category;

class UpdateCategoryRequest extends WriteCategoryRequest
{
    public function category(): ?Category
    {
        /** @var Category $category */
        $category = $this->route('category');

        return $category;
    }
}
