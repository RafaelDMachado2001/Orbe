<?php

declare(strict_types=1);

namespace App\Http\Requests\Transactions;

use App\Domain\Ledger\DTOs\TransactionFilters;
use App\Domain\Ledger\Enums\MovementOrigin;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'types' => ['sometimes', 'array'],
            'types.*' => [Rule::enum(TransactionType::class)],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => [Rule::enum(TransactionStatus::class)],
            'origins' => ['sometimes', 'array'],
            'origins.*' => [Rule::enum(MovementOrigin::class)],
            'categories' => ['sometimes', 'array'],
            'categories.*' => [
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId),
            ],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            'credit_card_id' => [
                'nullable',
                'integer',
                Rule::exists('credit_cards', 'id')->where('user_id', $userId),
            ],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,'.TransactionFilters::MAX_PER_PAGE],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'from.date_format' => 'A data inicial deve estar no formato AAAA-MM-DD.',
            'to.date_format' => 'A data final deve estar no formato AAAA-MM-DD.',
            'to.after_or_equal' => 'A data final não pode ser anterior à inicial.',
            'per_page.between' => 'A quantidade por página deve ficar entre 1 e '.TransactionFilters::MAX_PER_PAGE.'.',
        ];
    }

    /** Sem periodo informado, o extrato abre no mes corrente. */
    public function toFilters(): TransactionFilters
    {
        $today = CarbonImmutable::now();
        $search = trim((string) $this->query('search', ''));

        return new TransactionFilters(
            from: $this->dateOrDefault('from', $today->startOfMonth()),
            to: $this->dateOrDefault('to', $today->endOfMonth()),
            types: $this->enumList('types', TransactionType::class),
            statuses: $this->enumList('statuses', TransactionStatus::class),
            origins: $this->enumList('origins', MovementOrigin::class),
            categoryIds: $this->integerList('categories'),
            accountId: $this->integer('account_id') ?: null,
            creditCardId: $this->integer('credit_card_id') ?: null,
            search: $search === '' ? null : $search,
            page: max(1, (int) $this->query('page', 1)),
            perPage: (int) $this->query('per_page', 25),
        );
    }

    private function dateOrDefault(string $key, CarbonImmutable $fallback): CarbonImmutable
    {
        $value = $this->query($key);

        if (! is_string($value) || $value === '') {
            return $fallback;
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
    }

    /**
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return list<TEnum>
     */
    private function enumList(string $key, string $enum): array
    {
        $values = $this->query($key);

        if (! is_array($values)) {
            return [];
        }

        $parsed = [];

        foreach ($values as $value) {
            $case = is_string($value) ? $enum::tryFrom($value) : null;

            if ($case !== null) {
                $parsed[] = $case;
            }
        }

        return $parsed;
    }

    /** @return list<int> */
    private function integerList(string $key): array
    {
        $values = $this->query($key);

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $value): int => (int) $value, $values));
    }
}
