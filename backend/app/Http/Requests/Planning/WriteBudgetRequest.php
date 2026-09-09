<?php

declare(strict_types=1);

namespace App\Http\Requests\Planning;

use App\Domain\Planning\DTOs\BudgetData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class WriteBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->user()->id;

        return ['category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('user_id', $id)->where('type', 'despesa'), Rule::unique('budgets', 'category_id')->where(fn ($query) => $query->where('user_id', $id)->where('reference_month', $this->string('reference_month')->append('-01')->toString()))->ignore($this->route('budget'))], 'reference_month' => ['required', 'date_format:Y-m'], 'limit_amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99']];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return ['category_id.required' => 'Escolha a categoria.', 'category_id.exists' => 'Escolha uma categoria de despesa da sua lista.', 'category_id.unique' => 'Esta categoria já tem um orçamento neste mês.', 'reference_month.required' => 'Informe o mês de referência.', 'limit_amount.required' => 'Informe o limite do orçamento.', 'limit_amount.gt' => 'Informe um limite maior que zero.'];
    }

    public function toData(): BudgetData
    {
        return new BudgetData($this->integer('category_id'), $this->string('reference_month')->toString(), (string) round((float) $this->input('limit_amount'), 2));
    }
}
