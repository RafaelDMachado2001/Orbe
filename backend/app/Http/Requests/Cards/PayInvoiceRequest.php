<?php

declare(strict_types=1);

namespace App\Http\Requests\Cards;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pagamento de fatura.
 *
 * Sem valor informado, paga o saldo devedor inteiro — o caso comum. Um valor
 * menor registra pagamento parcial e a fatura continua devendo a diferenca; a
 * conferencia contra o saldo restante fica na Action, que e quem conhece a
 * fatura.
 */
class PayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'paid_at' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Escolha a conta que vai pagar a fatura.',
            'amount.gt' => 'O valor do pagamento precisa ser maior que zero.',
            'paid_at.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
        ];
    }

    public function amount(): ?float
    {
        $amount = $this->input('amount');

        return $amount === null || $amount === '' ? null : round((float) $amount, 2);
    }

    public function paidAt(): ?CarbonImmutable
    {
        $paidAt = $this->input('paid_at');

        return is_string($paidAt) && $paidAt !== ''
            ? CarbonImmutable::createFromFormat('Y-m-d', $paidAt)->startOfDay()
            : null;
    }
}
