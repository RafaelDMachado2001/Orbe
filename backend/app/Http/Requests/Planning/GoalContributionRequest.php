<?php

declare(strict_types=1);

namespace App\Http\Requests\Planning;

use App\Domain\Planning\DTOs\GoalContributionData;
use App\Domain\Planning\Models\Goal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Meta com conta vinculada exige a conta de origem — sem ela nao ha para
 * onde a transferencia ir. Meta sem conta vinculada nao aceita origem: nao
 * ha transferencia nenhuma para fazer, so o registro de progresso.
 */
class GoalContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $goal = $this->goal();
        $id = $this->user()->id;
        $hasAccount = $goal->account_id !== null;

        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'contributed_at' => ['nullable', 'date'],
            // Duas regras completas, nao uma so condicional: combinar
            // 'prohibited' com 'integer'/'exists' faria as duas ultimas
            // rodarem contra null mesmo quando a meta nao tem conta (nulo nao
            // vira "ausente" sozinho sem o 'nullable' explicito).
            'source_account_id' => $hasAccount
                ? [
                    'required',
                    'integer',
                    Rule::exists('accounts', 'id')->where('user_id', $id),
                    Rule::notIn([$goal->account_id]),
                ]
                : ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.required' => 'Informe o valor do aporte.',
            'amount.gt' => 'Informe um valor maior que zero.',
            'source_account_id.required' => 'Escolha de onde sai o dinheiro.',
            'source_account_id.prohibited' => 'Esta meta não tem conta vinculada — não há transferência a fazer.',
            'source_account_id.not_in' => 'A conta de origem precisa ser diferente da conta da meta.',
        ];
    }

    public function toData(): GoalContributionData
    {
        return new GoalContributionData(
            amount: round((float) $this->input('amount'), 2),
            contributedAt: $this->filled('contributed_at')
                ? CarbonImmutable::parse($this->string('contributed_at')->toString())
                : CarbonImmutable::now(),
            sourceAccountId: $this->integer('source_account_id') ?: null,
        );
    }

    private function goal(): Goal
    {
        /** @var Goal $goal */
        $goal = $this->route('goal');

        return $goal;
    }
}
