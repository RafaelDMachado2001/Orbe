<?php

declare(strict_types=1);

namespace App\Http\Requests\Recurrences;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class MaterializeRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'month' => ['required', 'string', 'date_format:Y-m'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'month.required' => 'Informe o mês a lançar.',
            'month.date_format' => 'O mês deve estar no formato AAAA-MM.',
        ];
    }

    public function month(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m', (string) $this->input('month'))->startOfMonth();
    }
}
