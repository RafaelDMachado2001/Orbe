<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnnualReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['year' => ['nullable', 'integer', 'min:2000', 'max:'.(int) now()->addYear()->format('Y')]];
    }

    public function year(): int
    {
        return $this->integer('year') ?: (int) now()->format('Y');
    }
}
