<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Domain\Ledger\DTOs\CategoryData;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Models\Category;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regras comuns a criar e editar uma categoria.
 *
 * A arvore tem dois niveis de proposito. "Casa > Aluguel" e util; "Casa >
 * Fixas > Moradia > Aluguel" e uma pasta dentro de outra que ninguem lembra de
 * abrir, e obrigaria todo relatorio a decidir em qual nivel agregar.
 */
abstract class WriteCategoryRequest extends FormRequest
{
    /** A categoria sendo editada, ou null na criacao. */
    abstract public function category(): ?Category;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->user()->id;
        $current = $this->category();

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:60',
                Rule::unique('categories', 'name')
                    ->where('user_id', $userId)
                    ->where('parent_id', $this->parentId())
                    ->ignore($current?->id),
            ],
            'type' => ['required', Rule::enum(CategoryType::class)],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:40'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Dê um nome à categoria.',
            'name.unique' => 'Já existe uma categoria com esse nome no mesmo nível.',
            'type.required' => 'Escolha se a categoria é de receita ou de despesa.',
            'color.required' => 'Escolha uma cor.',
            'color.regex' => 'A cor deve estar no formato #RRGGBB.',
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $parentId = $this->parentId();
                $current = $this->category();

                if ($parentId === null) {
                    return;
                }

                if ($current !== null && $parentId === $current->id) {
                    $validator->errors()->add('parent_id', 'Uma categoria não pode ser mãe de si mesma.');

                    return;
                }

                $parent = Category::query()->find($parentId);

                if ($parent === null) {
                    return;
                }

                if ($parent->parent_id !== null) {
                    $validator->errors()->add(
                        'parent_id',
                        'Uma subcategoria não pode ter subcategorias. Escolha uma categoria principal.',
                    );

                    return;
                }

                if ($parent->type !== $this->typeValue()) {
                    $validator->errors()->add(
                        'parent_id',
                        "A categoria principal escolhida é de {$parent->type->label()}.",
                    );

                    return;
                }

                if ($current !== null && $current->children()->exists()) {
                    $validator->errors()->add(
                        'parent_id',
                        'Esta categoria já tem subcategorias e por isso não pode virar subcategoria.',
                    );
                }
            },
        ];
    }

    public function toData(): CategoryData
    {
        $icon = trim((string) $this->input('icon'));

        return new CategoryData(
            name: trim((string) $this->input('name')),
            type: $this->typeValue(),
            color: strtoupper((string) $this->input('color')),
            icon: $icon === '' ? null : $icon,
            parentId: $this->parentId(),
        );
    }

    private function typeValue(): CategoryType
    {
        return $this->enum('type', CategoryType::class) ?? CategoryType::Despesa;
    }

    private function parentId(): ?int
    {
        return $this->integer('parent_id') ?: null;
    }
}
