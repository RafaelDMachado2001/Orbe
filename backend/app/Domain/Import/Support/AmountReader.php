<?php

declare(strict_types=1);

namespace App\Domain\Import\Support;

/**
 * Le o valor monetario como ele sai de um extrato, sem exigir um formato.
 *
 * O mesmo arquivo pode trazer "-284,90", "(284.90)", "R$ 1.234,56" ou
 * "284,90 D". Normalizar aqui, uma vez, evita que cada parser invente a sua
 * propria interpretacao de virgula.
 */
final class AmountReader
{
    /** Devolve o valor com sinal, ou null quando o texto nao e um numero. */
    public static function parse(string $raw): ?float
    {
        $text = trim($raw);

        if ($text === '') {
            return null;
        }

        $isNegative = self::detectNegative($text);

        // Sobra apenas o numero e os separadores: fora simbolo de moeda,
        // espaco (inclusive o nao separavel), parenteses e o marcador D/C.
        $digits = preg_replace('/[^0-9.,]/u', '', $text) ?? '';

        if ($digits === '' || preg_match('/\d/', $digits) !== 1) {
            return null;
        }

        $value = (float) self::normalizeSeparators($digits);

        return $isNegative ? -$value : $value;
    }

    /**
     * Extratos marcam saida de quatro jeitos: sinal na frente, sinal atras,
     * parenteses em volta e a letra D de debito.
     */
    private static function detectNegative(string $text): bool
    {
        if (str_starts_with($text, '-') || str_ends_with($text, '-')) {
            return true;
        }

        if (str_starts_with($text, '(') && str_ends_with($text, ')')) {
            return true;
        }

        return preg_match('/\d\s*D$/iu', $text) === 1;
    }

    /**
     * Decide qual separador e o decimal e devolve o numero em ponto.
     *
     * Com os dois presentes, o ultimo e o decimal ("1.234,56" e "1,234.56").
     * Com um so, ele e o decimal — exceto quando separa exatamente tres casas
     * ("1.234"), que e milhar, ou quando aparece mais de uma vez.
     */
    private static function normalizeSeparators(string $digits): string
    {
        $dots = substr_count($digits, '.');
        $commas = substr_count($digits, ',');

        $decimal = match (true) {
            $dots > 0 && $commas > 0 => strrpos($digits, '.') > strrpos($digits, ',') ? '.' : ',',
            $dots === 1 && strlen(substr($digits, (int) strrpos($digits, '.') + 1)) !== 3 => '.',
            $commas === 1 && strlen(substr($digits, (int) strrpos($digits, ',') + 1)) !== 3 => ',',
            default => null,
        };

        if ($decimal === null) {
            return str_replace(['.', ','], '', $digits);
        }

        $thousands = $decimal === '.' ? ',' : '.';

        return str_replace($decimal, '.', str_replace($thousands, '', $digits));
    }
}
