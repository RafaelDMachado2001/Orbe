<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\DTOs\CategoryData;
use App\Domain\Ledger\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Edita uma categoria.
 *
 * O tipo de uma categoria de sistema nao muda: ela e o destino padrao de algo
 * (a de emprestimos, por exemplo) e virar receita quebraria quem aponta para
 * ela. Nome, cor, icone e lugar na arvore, sim — a categoria e do usuario.
 *
 * Trocar o tipo de uma categoria com filhas leva as filhas junto: elas nunca
 * podem discordar da mae, senao o seletor de despesas passaria a oferecer uma
 * subcategoria de receita.
 */
final class UpdateCategoryAction
{
    public function handle(Category $category, CategoryData $data): Category
    {
        return DB::transaction(function () use ($category, $data): Category {
            $type = $category->is_system ? $category->type : $data->type;

            $category->forceFill([
                'parent_id' => $data->parentId,
                'name' => $data->name,
                'type' => $type,
                'color' => $data->color,
                'icon' => $data->icon,
            ])->save();

            if ($category->wasChanged('type')) {
                Category::query()
                    ->where('parent_id', $category->id)
                    ->update(['type' => $type->value]);
            }

            return $category->refresh();
        });
    }
}
