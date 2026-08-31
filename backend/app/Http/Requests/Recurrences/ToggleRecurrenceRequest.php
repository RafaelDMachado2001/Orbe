<?php

declare(strict_types=1);

namespace App\Http\Requests\Recurrences;

use Illuminate\Foundation\Http\FormRequest;

class ToggleRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function isActive(): bool
    {
        return $this->boolean('is_active');
    }
}
