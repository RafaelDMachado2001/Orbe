<?php

declare(strict_types=1);

namespace App\Http\Requests\Cards;

use App\Domain\Cards\DTOs\CreditCardData;
use App\Domain\Cards\Enums\CardBrand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regras de cadastro e edicao de cartao.
 *
 * Fechamento e vencimento ficam entre 1 e 28: os dias 29 a 31 nao existem em
 * todo mes, e o ciclo do cartao passaria a variar de mes para mes.
 */
class WriteCreditCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'bank_id' => [
                'required',
                'integer',
                Rule::exists('banks', 'id')->where('user_id', $userId),
            ],
            'payment_account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'nickname' => ['required', 'string', 'min:2', 'max:60'],
            'brand' => ['required', Rule::enum(CardBrand::class)],
            'last_four' => ['required', 'string', 'digits:4'],
            'limit_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'closing_day' => ['required', 'integer', 'between:1,28'],
            'due_day' => ['required', 'integer', 'between:1,28'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'bank_id.required' => 'Escolha o banco do cartão.',
            'nickname.required' => 'Dê um apelido ao cartão.',
            'brand.required' => 'Escolha a bandeira.',
            'last_four.digits' => 'Informe os quatro últimos dígitos do cartão.',
            'limit_amount.required' => 'Informe o limite do cartão.',
            'closing_day.between' => 'O dia de fechamento precisa ficar entre 1 e 28.',
            'due_day.between' => 'O dia de vencimento precisa ficar entre 1 e 28.',
            'color.regex' => 'A cor deve estar no formato #RRGGBB.',
        ];
    }

    public function toData(): CreditCardData
    {
        return new CreditCardData(
            bankId: $this->integer('bank_id'),
            nickname: trim((string) $this->input('nickname')),
            brand: $this->enum('brand', CardBrand::class),
            lastFour: (string) $this->input('last_four'),
            limitAmount: round((float) $this->input('limit_amount'), 2),
            closingDay: $this->integer('closing_day'),
            dueDay: $this->integer('due_day'),
            color: (string) $this->input('color', '#A07CFF'),
            paymentAccountId: $this->integer('payment_account_id') ?: null,
        );
    }
}
