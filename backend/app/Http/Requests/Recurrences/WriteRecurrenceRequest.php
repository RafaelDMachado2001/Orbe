<?php

declare(strict_types=1);

namespace App\Http\Requests\Recurrences;

use App\Domain\Ledger\DTOs\RecurrenceData;
use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\RecurrenceFrequency;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regras de cadastro e edicao de uma despesa ou receita fixa.
 *
 * A origem e exclusiva: conta ou cartao. O `prohibited` condicional garante
 * que o cliente nao envie os dois e deixe a Action decidir — a resposta
 * precisa apontar o campo errado.
 */
class WriteRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->user()->id;
        $isOnCard = $this->source() === 'cartao';

        return [
            'source_kind' => ['required', Rule::in(['conta', 'cartao'])],
            'description' => ['required', 'string', 'min:2', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'type' => ['required', Rule::enum(TransactionType::class), 'not_in:transferencia'],
            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'interval' => ['required', 'integer', 'between:1,24'],
            'day_of_month' => [
                $this->input('frequency') === RecurrenceFrequency::Mensal->value ? 'required' : 'nullable',
                'integer',
                'between:1,28',
            ],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId),
            ],
            // Cada ramo declara as proprias regras. Misturar "proibido" com
            // "existe" na mesma lista faria o exists rodar sobre o nulo que o
            // cliente manda para o campo que nao se aplica.
            ...$this->sourceRules($isOnCard, $userId),
        ];
    }

    /**
     * A origem e exclusiva. Quando a regra sai do cartao, a conta e a forma de
     * pagamento nao se aplicam — e vice-versa. No cartao o metodo e sempre
     * credito, definido pela Action.
     *
     * @return array<string, mixed>
     */
    private function sourceRules(bool $isOnCard, int $userId): array
    {
        if ($isOnCard) {
            return [
                'account_id' => ['nullable', 'prohibited'],
                'credit_card_id' => [
                    'required',
                    'integer',
                    Rule::exists('credit_cards', 'id')->where('user_id', $userId),
                ],
                'method' => ['nullable', 'prohibited'],
            ];
        }

        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'credit_card_id' => ['nullable', 'prohibited'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'source_kind.required' => 'Informe se a regra sai da conta ou do cartão.',
            'description.required' => 'Descreva a despesa fixa.',
            'amount.required' => 'Informe o valor.',
            'amount.gt' => 'O valor precisa ser maior que zero.',
            'type.not_in' => 'Transferência não vira despesa fixa.',
            'frequency.required' => 'Escolha a frequência.',
            'interval.between' => 'O intervalo aceita de 1 a 24.',
            'day_of_month.required' => 'Escolha o dia do mês.',
            'day_of_month.between' => 'O dia do mês precisa ficar entre 1 e 28.',
            'starts_on.required' => 'Informe a partir de quando a regra vale.',
            'ends_on.after_or_equal' => 'O fim da vigência não pode ser anterior ao início.',
            'account_id.required' => 'Escolha a conta.',
            'credit_card_id.required' => 'Escolha o cartão.',
        ];
    }

    /**
     * Cartao de credito nao recebe receita, e categoria de receita nao
     * classifica despesa. As duas checagens ficam aqui para a resposta poder
     * apontar o campo.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->source() === 'cartao' && $this->input('type') === TransactionType::Receita->value) {
                    $validator->errors()->add('type', 'Cartão de crédito não recebe receita.');
                }

                $this->validateCategoryType($validator);
            },
        ];
    }

    private function validateCategoryType(Validator $validator): void
    {
        $categoryId = $this->integer('category_id');

        if ($categoryId === 0) {
            return;
        }

        $expected = $this->input('type') === TransactionType::Receita->value
            ? CategoryType::Receita
            : CategoryType::Despesa;

        $category = Category::query()->find($categoryId);

        if ($category !== null && $category->type !== $expected) {
            $validator->errors()->add(
                'category_id',
                "Escolha uma categoria de {$expected->label()}.",
            );
        }
    }

    public function toData(): RecurrenceData
    {
        $isOnCard = $this->source() === 'cartao';
        $endsOn = $this->input('ends_on');

        return new RecurrenceData(
            description: trim((string) $this->input('description')),
            amount: round((float) $this->input('amount'), 2),
            type: $this->enum('type', TransactionType::class),
            frequency: $this->enum('frequency', RecurrenceFrequency::class),
            startsOn: CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('starts_on'))->startOfDay(),
            interval: $this->integer('interval'),
            dayOfMonth: $this->integer('day_of_month') ?: null,
            endsOn: is_string($endsOn) && $endsOn !== ''
                ? CarbonImmutable::createFromFormat('Y-m-d', $endsOn)->startOfDay()
                : null,
            categoryId: $this->integer('category_id') ?: null,
            accountId: $isOnCard ? null : $this->integer('account_id'),
            creditCardId: $isOnCard ? $this->integer('credit_card_id') : null,
            method: $isOnCard ? null : $this->enum('method', PaymentMethod::class),
        );
    }

    private function source(): string
    {
        return (string) $this->input('source_kind', 'conta');
    }
}
