<?php

declare(strict_types=1);

use App\Domain\Import\Support\AmountReader;
use App\Domain\Import\Support\DateReader;

it('le o valor em qualquer notacao que o extrato usar', function (string $raw, ?float $expected): void {
    expect(AmountReader::parse($raw))->toBe($expected);
})->with([
    'ponto decimal' => ['-284.90', -284.90],
    'virgula decimal' => ['-284,90', -284.90],
    'milhar brasileiro' => ['1.234,56', 1234.56],
    'milhar americano' => ['1,234.56', 1234.56],
    'milhar sem centavos' => ['1.234', 1234.0],
    'moeda e espaco' => ['R$ 7.200,00', 7200.0],
    'parenteses sao negativos' => ['(284,90)', -284.90],
    'sinal no fim' => ['284,90-', -284.90],
    'marcador de debito' => ['284,90 D', -284.90],
    'positivo explicito' => ['+120.00', 120.0],
    'inteiro puro' => ['500', 500.0],
    'texto nao e valor' => ['SALDO ANTERIOR', null],
    'vazio' => ['   ', null],
]);

it('le a data em qualquer notacao que o extrato usar', function (string $raw, ?string $expected): void {
    expect(DateReader::parse($raw)?->toDateString())->toBe($expected);
})->with([
    'ofx com fuso' => ['20260803120000[-3:BRT]', '2026-08-03'],
    'ofx cru' => ['20260803', '2026-08-03'],
    'brasileiro' => ['03/08/2026', '2026-08-03'],
    'brasileiro com dois digitos' => ['03/08/26', '2026-08-03'],
    'iso' => ['2026-08-03', '2026-08-03'],
    'com ponto' => ['03.08.2026', '2026-08-03'],
    'texto nao e data' => ['SALDO', null],
    'data impossivel' => ['31/02/2026', null],
]);

/**
 * Sem esta regra um arquivo em mm/dd/yyyy viraria datas invalidas ou, pior,
 * datas validas no mes errado.
 */
it('inverte dia e mes so quando a ordem brasileira seria impossivel', function (): void {
    expect(DateReader::parse('08/03/2026')?->toDateString())->toBe('2026-03-08')
        ->and(DateReader::parse('03/28/2026')?->toDateString())->toBe('2026-03-28');
});
