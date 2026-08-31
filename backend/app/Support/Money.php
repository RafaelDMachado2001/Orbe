<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Utilitarios de dinheiro. Somas sensiveis acontecem em centavos (inteiro)
 * para nao acumular erro de ponto flutuante; a conversao de volta para reais
 * so ocorre na fronteira da aplicacao.
 */
final class Money
{
    public static function toCents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function toReais(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Divide um valor em N parcelas sem perder centavos: o residuo da divisao
     * e distribuido nas primeiras parcelas.
     *
     * @return list<int> valores em centavos
     */
    public static function split(int $cents, int $parts): array
    {
        if ($parts < 1) {
            throw new \InvalidArgumentException('O numero de parcelas deve ser maior que zero.');
        }

        $base = intdiv($cents, $parts);
        $remainder = $cents - ($base * $parts);

        return array_map(
            static fn (int $index): int => $base + ($index < $remainder ? 1 : 0),
            range(0, $parts - 1),
        );
    }

    /** @param iterable<float|int|string> $amounts */
    public static function sum(iterable $amounts): float
    {
        $cents = 0;

        foreach ($amounts as $amount) {
            $cents += self::toCents($amount);
        }

        return self::toReais($cents);
    }
}
