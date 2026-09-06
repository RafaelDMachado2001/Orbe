<?php

declare(strict_types=1);

namespace App\Http\Requests\Banking;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class AccountIndexRequest extends FormRequest
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
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'month.date_format' => 'O mês deve estar no formato AAAA-MM.',
        ];
    }

    public function month(): CarbonImmutable
    {
        $month = $this->query('month');

        if (! is_string($month) || $month === '') {
            return CarbonImmutable::now()->startOfMonth();
        }

        return CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
    }

    public function includeArchived(): bool
    {
        return $this->boolean('archived');
    }
}
