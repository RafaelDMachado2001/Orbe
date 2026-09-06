<?php

declare(strict_types=1);

namespace App\Http\Requests\Banking;

use App\Domain\Banking\DTOs\BalanceAdjustmentData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A pessoa informa o saldo que o banco mostra, nao a diferenca. Quem calcula a
 * diferenca e a Action, contra o saldo da conta na data do ajuste — assim dois
 * envios em sequencia nao dobram a correcao.
 */
class AdjustBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'balance' => ['required', 'numeric', 'min:-99999999.99', 'max:99999999.99'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'balance.required' => 'Informe o saldo que o banco mostra.',
            'date.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
            'notes.max' => 'A observação pode ter até 255 caracteres.',
        ];
    }

    public function toData(): BalanceAdjustmentData
    {
        $date = $this->input('date');
        $notes = $this->input('notes');

        return new BalanceAdjustmentData(
            targetBalance: round((float) $this->input('balance'), 2),
            date: is_string($date) && $date !== ''
                ? CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay()
                : CarbonImmutable::now()->startOfDay(),
            notes: is_string($notes) && trim($notes) !== '' ? trim($notes) : null,
        );
    }
}
