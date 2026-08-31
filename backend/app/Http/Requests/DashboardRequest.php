<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'month' => ['sometimes', 'string', 'date_format:Y-m'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'month.date_format' => 'O mês deve estar no formato AAAA-MM.',
        ];
    }

    /** Mes solicitado, ou o mes corrente quando nao informado. */
    public function month(): CarbonImmutable
    {
        $month = $this->query('month');

        if (! is_string($month) || $month === '') {
            return CarbonImmutable::now()->startOfMonth();
        }

        return CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
    }
}
