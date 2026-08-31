<?php

declare(strict_types=1);

namespace App\Domain\Import\Support;

use Carbon\CarbonImmutable;

/**
 * Le a data como ela sai de um extrato.
 *
 * OFX usa `20260803120000[-3:BRT]`; CSV de banco brasileiro usa `03/08/2026`;
 * planilha exportada de ferramenta gringa usa `2026-08-03`. Todas caem aqui.
 */
final class DateReader
{
    public static function parse(string $raw): ?CarbonImmutable
    {
        $text = trim($raw);

        if ($text === '') {
            return null;
        }

        // OFX carimba fuso e hora depois da data: "20260803120000[-3:BRT]".
        // Os oito primeiros digitos sao a data e bastam — o extrato e diario.
        if (preg_match('/^(\d{8})/', $text, $match) === 1) {
            return self::build((int) substr($match[1], 6, 2), (int) substr($match[1], 4, 2), (int) substr($match[1], 0, 4));
        }

        // ISO: 2026-08-03 (ou 2026/08/03).
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $text, $match) === 1) {
            return self::build((int) $match[3], (int) $match[2], (int) $match[1]);
        }

        // Brasileiro: 03/08/2026 ou 03/08/26.
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})/', $text, $match) === 1) {
            $first = (int) $match[1];
            $second = (int) $match[2];

            // Dia e mes na ordem brasileira; so inverte quando o segundo campo
            // nao pode ser mes, o que denuncia um arquivo em mm/dd/yyyy.
            [$day, $month] = $second > 12 && $first <= 12 ? [$second, $first] : [$first, $second];

            return self::build($day, $month, self::expandYear((int) $match[3]));
        }

        return null;
    }

    private static function build(int $day, int $month, int $year): ?CarbonImmutable
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0);
    }

    /** Ano de dois digitos: 26 e 2026, 98 e 1998. */
    private static function expandYear(int $year): int
    {
        return match (true) {
            $year >= 1000 => $year,
            $year <= 69 => 2000 + $year,
            default => 1900 + $year,
        };
    }
}
