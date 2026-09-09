<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['horizon' => ['nullable', 'integer', 'min:1', 'max:24'], 'from' => ['nullable', 'date_format:Y-m']];
    }

    public function horizon(): int
    {
        return $this->integer('horizon') ?: 6;
    }

    public function from(): ?CarbonImmutable
    {
        return $this->input('from') ? CarbonImmutable::createFromFormat('Y-m-d', $this->input('from').'-01') : null;
    }
}
