<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

use Carbon\CarbonImmutable;

enum RecurrenceFrequency: string
{
    case Diaria = 'diaria';
    case Semanal = 'semanal';
    case Mensal = 'mensal';
    case Anual = 'anual';

    public function label(): string
    {
        return match ($this) {
            self::Diaria => 'Diária',
            self::Semanal => 'Semanal',
            self::Mensal => 'Mensal',
            self::Anual => 'Anual',
        };
    }

    public function advance(CarbonImmutable $from, int $interval): CarbonImmutable
    {
        return match ($this) {
            self::Diaria => $from->addDays($interval),
            self::Semanal => $from->addWeeks($interval),
            self::Mensal => $from->addMonthsNoOverflow($interval),
            self::Anual => $from->addYears($interval),
        };
    }

    /** Quantas ocorrencias essa regra gera dentro de um mes. */
    public function occurrencesPerMonth(int $interval): float
    {
        $perMonth = match ($this) {
            self::Diaria => 30.0,
            self::Semanal => 52 / 12,
            self::Mensal => 1.0,
            self::Anual => 1 / 12,
        };

        return $perMonth / max($interval, 1);
    }
}
