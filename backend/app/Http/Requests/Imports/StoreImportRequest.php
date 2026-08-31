<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

use App\Domain\Import\DTOs\ImportCommitData;
use App\Domain\Import\DTOs\ImportedRow;
use App\Domain\Import\Enums\ImportFormat;
use App\Domain\Ledger\Enums\MovementDirection;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * As linhas que o usuario confirmou.
 *
 * O arquivo nao volta: a tela ja o leu e o que chega aqui sao os lancamentos
 * revisados, possivelmente com a categoria trocada a mao. Reprocessar o
 * arquivo no servidor descartaria justamente essas correcoes.
 */
class StoreImportRequest extends FormRequest
{
    private const MAX_ROWS = 2000;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'filename' => ['required', 'string', 'max:255'],
            'format' => ['required', Rule::enum(ImportFormat::class)],
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
            'skipped_count' => ['sometimes', 'integer', 'min:0'],

            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'rows.*.date' => ['required', 'date_format:Y-m-d'],
            'rows.*.description' => ['required', 'string', 'max:255'],
            'rows.*.amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'rows.*.direction' => ['required', Rule::enum(MovementDirection::class)],
            'rows.*.category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('account_id') === $this->filled('credit_card_id')) {
                $validator->errors()->add(
                    'account_id',
                    'Escolha a conta ou o cartão que vai receber os lançamentos.',
                );
            }

            // Uma fatura so recebe compra. Entrada em cartao seria estorno ou
            // pagamento, que a tela de Cartoes registra — a de importacao ja
            // bloqueia essas linhas, e a regra e repetida aqui porque a
            // requisicao pode chegar sem passar pela tela.
            if (! $this->filled('credit_card_id')) {
                return;
            }

            foreach ((array) $this->input('rows', []) as $index => $row) {
                if (($row['direction'] ?? null) === MovementDirection::Entrada->value) {
                    $validator->errors()->add(
                        "rows.{$index}.direction",
                        'Crédito na fatura não vira compra. Registre o pagamento pela tela de Cartões.',
                    );
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rows.required' => 'Selecione ao menos um lançamento para importar.',
            'rows.min' => 'Selecione ao menos um lançamento para importar.',
            'rows.max' => 'Importe no máximo '.self::MAX_ROWS.' lançamentos por vez.',
        ];
    }

    public function toData(): ImportCommitData
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->input('rows');

        return new ImportCommitData(
            userId: $this->user()->id,
            filename: (string) $this->input('filename'),
            format: $this->enum('format', ImportFormat::class),
            rows: array_map(
                static fn (array $row): ImportedRow => new ImportedRow(
                    date: CarbonImmutable::parse((string) $row['date'])->startOfDay(),
                    description: trim((string) $row['description']),
                    amount: round((float) $row['amount'], 2),
                    direction: MovementDirection::from((string) $row['direction']),
                    categoryId: isset($row['category_id']) ? (int) $row['category_id'] : null,
                ),
                $rows,
            ),
            accountId: $this->filled('account_id') ? $this->integer('account_id') : null,
            creditCardId: $this->filled('credit_card_id') ? $this->integer('credit_card_id') : null,
            skippedCount: $this->integer('skipped_count'),
        );
    }
}
