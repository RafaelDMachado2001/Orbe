<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Domain\Ledger\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reassign_to' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    /** Para onde levar os lancamentos da categoria excluida. */
    public function destination(): ?Category
    {
        $id = $this->integer('reassign_to');

        return $id === 0 ? null : Category::query()->find($id);
    }
}
