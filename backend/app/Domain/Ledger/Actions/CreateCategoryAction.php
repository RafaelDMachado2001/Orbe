<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\DTOs\CategoryData;
use App\Domain\Ledger\Models\Category;
use App\Models\User;

final class CreateCategoryAction
{
    public function handle(User $user, CategoryData $data): Category
    {
        return Category::query()->create([
            'user_id' => $user->id,
            'parent_id' => $data->parentId,
            'name' => $data->name,
            'type' => $data->type,
            'color' => $data->color,
            'icon' => $data->icon,
            'is_system' => false,
        ]);
    }
}
