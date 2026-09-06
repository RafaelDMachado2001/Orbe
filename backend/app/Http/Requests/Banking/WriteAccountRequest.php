<?php

declare(strict_types=1);

namespace App\Http\Requests\Banking;

use App\Domain\Banking\DTOs\AccountData;
use App\Domain\Banking\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regras de cadastro e edicao de conta.
 *
 * O saldo inicial e pedido apenas na criacao: e o ponto de partida do
 * historico, e reescreve-lo mudaria todos os saldos ja vistos. Depois disso a
 * conciliacao com o extrato do banco acontece pelo ajuste de saldo.
 */
class WriteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bank_id' => [
                'required',
                'integer',
                Rule::exists('banks', 'id')->where('user_id', $this->user()->id),
            ],
            'nickname' => ['required', 'string', 'min:2', 'max:60'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'initial_balance' => [
                $this->isCreating() ? 'required' : 'prohibited',
                'numeric',
                'min:-99999999.99',
                'max:99999999.99',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'bank_id.required' => 'Escolha o banco da conta.',
            'bank_id.exists' => 'Escolha um banco da sua lista.',
            'nickname.required' => 'Dê um apelido à conta.',
            'type.required' => 'Escolha o tipo de conta.',
            'initial_balance.required' => 'Informe o saldo que a conta tem hoje.',
            'initial_balance.prohibited' => 'O saldo inicial não muda depois da criação. '.
                'Use "Ajustar saldo" para bater com o extrato do banco.',
        ];
    }

    public function toData(): AccountData
    {
        return new AccountData(
            bankId: $this->integer('bank_id'),
            nickname: trim((string) $this->input('nickname')),
            type: $this->enum('type', AccountType::class),
            initialBalance: $this->isCreating()
                ? round((float) $this->input('initial_balance'), 2)
                : null,
        );
    }

    private function isCreating(): bool
    {
        return $this->route('account') === null;
    }
}
