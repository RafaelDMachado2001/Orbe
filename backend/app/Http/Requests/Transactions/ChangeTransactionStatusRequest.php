<?php

declare(strict_types=1);

namespace App\Http\Requests\Transactions;

use App\Domain\Ledger\Enums\TransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTransactionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TransactionStatus::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Informe o novo status do lançamento.',
        ];
    }

    public function status(): TransactionStatus
    {
        return $this->enum('status', TransactionStatus::class);
    }
}
