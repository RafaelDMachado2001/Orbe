<?php

declare(strict_types=1);

namespace App\Http\Requests\Transactions;

use App\Domain\Ledger\Enums\EntryKind;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends WriteTransactionRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(EntryKind::class)],
            ...parent::rules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'kind.required' => 'Informe o tipo de lançamento.',
        ];
    }

    /**
     * A aba escolhida no formulario. Um valor invalido vira despesa apenas
     * para que as demais regras consigam rodar e devolver todos os erros de
     * uma vez; a validacao de kind ja reprovou a requisicao.
     */
    public function kind(): EntryKind
    {
        return $this->enum('kind', EntryKind::class) ?? EntryKind::Despesa;
    }
}
