<?php

declare(strict_types=1);

namespace App\Http\Requests\Imports;

use App\Domain\Import\DTOs\CsvMapping;
use App\Domain\Import\DTOs\ImportSource;
use App\Domain\Import\Enums\ImportFormat;
use App\Domain\Import\Exceptions\StatementParseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * O arquivo enviado para conferencia.
 *
 * O tamanho e o numero de colunas sao barrados aqui, antes de o parser abrir o
 * conteudo: ler um arquivo de dezenas de megabytes para so entao recusa-lo
 * custaria a memoria do processo.
 */
class PreviewImportRequest extends FormRequest
{
    /** 4 MB cobre anos de extrato em texto com folga. */
    private const MAX_KILOBYTES = 4096;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES],
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
            // Mapeamento so chega na segunda ida: a primeira e o palpite do
            // parser, que a tela mostra e o usuario corrige se estiver errado.
            'mapping' => ['sometimes', 'array'],
            'mapping.date_column' => ['required_with:mapping', 'integer', 'min:0', 'max:99'],
            'mapping.description_column' => ['required_with:mapping', 'integer', 'min:0', 'max:99'],
            'mapping.amount_column' => ['required_with:mapping', 'integer', 'min:0', 'max:99'],
            'mapping.inflow_column' => ['nullable', 'integer', 'min:0', 'max:99'],
            'mapping.delimiter' => ['required_with:mapping', 'string', Rule::in([';', ',', "\t", '|'])],
            'mapping.has_header' => ['required_with:mapping', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasAccount = $this->filled('account_id');
            $hasCard = $this->filled('credit_card_id');

            if ($hasAccount === $hasCard) {
                $validator->errors()->add(
                    'account_id',
                    'Escolha a conta ou o cartão que vai receber os lançamentos.',
                );
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Escolha o arquivo do extrato.',
            'file.max' => 'O arquivo passa de 4 MB. Exporte o extrato em períodos menores.',
        ];
    }

    public function toSource(): ImportSource
    {
        $file = $this->file('file');
        $extension = mb_strtolower($file->getClientOriginalExtension());
        $format = ImportFormat::fromExtension($extension);

        if ($format === null) {
            throw StatementParseException::unsupportedExtension($extension);
        }

        $contents = file_get_contents($file->getRealPath());

        if ($contents === false || trim($contents) === '') {
            throw StatementParseException::emptyFile();
        }

        return new ImportSource(
            userId: $this->user()->id,
            contents: $contents,
            filename: mb_substr($file->getClientOriginalName(), 0, 255),
            format: $format,
            accountId: $this->filled('account_id') ? $this->integer('account_id') : null,
            creditCardId: $this->filled('credit_card_id') ? $this->integer('credit_card_id') : null,
            mapping: $this->mapping($format),
        );
    }

    private function mapping(ImportFormat $format): ?CsvMapping
    {
        if ($format !== ImportFormat::Csv || ! $this->has('mapping')) {
            return null;
        }

        /** @var array<string, mixed> $mapping */
        $mapping = $this->input('mapping');
        $inflow = $mapping['inflow_column'] ?? null;

        return new CsvMapping(
            dateColumn: (int) $mapping['date_column'],
            descriptionColumn: (int) $mapping['description_column'],
            amountColumn: (int) $mapping['amount_column'],
            inflowColumn: $inflow === null || $inflow === '' ? null : (int) $inflow,
            delimiter: (string) $mapping['delimiter'],
            hasHeader: filter_var($mapping['has_header'], FILTER_VALIDATE_BOOL),
        );
    }
}
