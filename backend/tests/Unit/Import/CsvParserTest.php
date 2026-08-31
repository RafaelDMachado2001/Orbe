<?php

declare(strict_types=1);

use App\Domain\Import\DTOs\CsvMapping;
use App\Domain\Import\Exceptions\StatementParseException;
use App\Domain\Import\Parsers\CsvParser;
use App\Domain\Ledger\Enums\MovementDirection;

it('adivinha separador e colunas pelo cabecalho', function (): void {
    $csv = <<<'CSV'
    Data;Histórico;Valor;Saldo
    03/08/2026;Mercado Pão de Açúcar;-284,90;5.715,10
    05/08/2026;Salário;7.200,00;12.915,10
    CSV;

    $statement = (new CsvParser)->parse($csv);

    expect($statement->entries)->toHaveCount(2)
        ->and($statement->mapping?->delimiter)->toBe(';')
        ->and($statement->mapping?->dateColumn)->toBe(0)
        ->and($statement->mapping?->descriptionColumn)->toBe(1)
        // "Saldo" e numerico e mora ao lado do valor; confundir os dois
        // importaria o acumulado da conta como se fosse o lancamento.
        ->and($statement->mapping?->amountColumn)->toBe(2)
        ->and($statement->headers)->toBe(['Data', 'Histórico', 'Valor', 'Saldo'])
        ->and($statement->entries[0]->amount)->toBe(284.90)
        ->and($statement->entries[0]->direction)->toBe(MovementDirection::Saida)
        ->and($statement->entries[1]->amount)->toBe(7200.0)
        ->and($statement->entries[1]->direction)->toBe(MovementDirection::Entrada);
});

it('entende o arquivo que separa debito de credito em duas colunas', function (): void {
    $csv = <<<'CSV'
    Data,Descricao,Debito,Credito
    2026-08-03,Uber,32.40,
    2026-08-05,Salario,,7200.00
    CSV;

    $statement = (new CsvParser)->parse($csv);

    expect($statement->mapping?->isSplitAmount())->toBeTrue()
        ->and($statement->entries[0]->direction)->toBe(MovementDirection::Saida)
        ->and($statement->entries[0]->amount)->toBe(32.40)
        ->and($statement->entries[1]->direction)->toBe(MovementDirection::Entrada)
        ->and($statement->entries[1]->amount)->toBe(7200.0);
});

it('deduz as colunas pelo conteudo quando o arquivo nao tem cabecalho', function (): void {
    $csv = <<<'CSV'
    03/08/2026;Mercado Pão de Açúcar;-284,90
    05/08/2026;Salário;7.200,00
    CSV;

    $statement = (new CsvParser)->parse($csv);

    expect($statement->mapping?->hasHeader)->toBeFalse()
        ->and($statement->headers)->toBe([])
        ->and($statement->entries)->toHaveCount(2)
        ->and($statement->entries[0]->description)->toBe('Mercado Pão de Açúcar');
});

it('respeita o mapeamento corrigido na tela em vez de adivinhar de novo', function (): void {
    $csv = <<<'CSV'
    Data;Valor;Histórico
    03/08/2026;-284,90;Mercado
    CSV;

    $statement = (new CsvParser)->parse($csv, new CsvMapping(
        dateColumn: 0,
        descriptionColumn: 2,
        amountColumn: 1,
        delimiter: ';',
        hasHeader: true,
    ));

    expect($statement->entries[0]->description)->toBe('Mercado')
        ->and($statement->entries[0]->amount)->toBe(284.90);
});

/**
 * Todo exportador de banco intercala rodape de saldo e linha em branco no meio
 * do arquivo. Elas nao tem data e nao sao lancamento.
 */
it('pula rodape, linha em branco e cabecalho repetido', function (): void {
    $csv = <<<'CSV'
    Data;Histórico;Valor
    03/08/2026;Mercado;-284,90

    ;SALDO ANTERIOR;5.000,00
    Data;Histórico;Valor
    04/08/2026;Uber;-32,40
    ;TOTAL;-317,30
    CSV;

    $entries = (new CsvParser)->parse($csv)->entries;

    expect($entries)->toHaveCount(2)
        ->and(array_map(fn ($entry) => $entry->description, $entries))->toBe(['Mercado', 'Uber']);
});

it('respeita campo entre aspas com o proprio separador dentro', function (): void {
    $csv = <<<'CSV'
    Data;Histórico;Valor
    03/08/2026;"Mercado; filial 2";-284,90
    CSV;

    expect((new CsvParser)->parse($csv)->entries[0]->description)->toBe('Mercado; filial 2');
});

it('recusa um CSV em que nao da para identificar as colunas', function (): void {
    (new CsvParser)->parse("a;b;c\nx;y;z");
})->throws(StatementParseException::class);
