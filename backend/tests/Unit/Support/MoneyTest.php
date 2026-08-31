<?php

declare(strict_types=1);

use App\Support\Money;

it('converte reais para centavos sem erro de ponto flutuante', function (): void {
    expect(Money::toCents(0.1 + 0.2))->toBe(30)
        ->and(Money::toCents('1234.56'))->toBe(123456)
        ->and(Money::toCents(19.99))->toBe(1999);
});

it('divide o valor em parcelas sem perder centavos', function (): void {
    $parts = Money::split(10000, 3);

    expect($parts)->toBe([3334, 3333, 3333])
        ->and(array_sum($parts))->toBe(10000);
});

it('distribui o residuo nas primeiras parcelas', function (): void {
    $parts = Money::split(1001, 4);

    expect($parts)->toBe([251, 250, 250, 250])
        ->and(array_sum($parts))->toBe(1001);
});

it('recusa um numero invalido de parcelas', function (): void {
    Money::split(1000, 0);
})->throws(InvalidArgumentException::class);

it('soma valores em centavos e devolve reais', function (): void {
    expect(Money::sum(['0.1', '0.2', 0.3]))->toBe(0.6);
});
