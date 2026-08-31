<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\RecurrenceFrequency;
use App\Domain\Ledger\Models\Recurrence;
use Carbon\CarbonImmutable;

/** @param array<string, mixed> $attributes */
function makeRecurrence(array $attributes = []): Recurrence
{
    $recurrence = new Recurrence;

    $recurrence->forceFill([
        'frequency' => RecurrenceFrequency::Mensal,
        'interval' => 1,
        'day_of_month' => null,
        'starts_on' => '2026-01-10',
        'ends_on' => null,
        'is_active' => true,
        ...$attributes,
    ]);

    return $recurrence;
}

/** @return list<string> */
function datesIn(Recurrence $recurrence, string $month): array
{
    return array_map(
        static fn (CarbonImmutable $date): string => $date->toDateString(),
        $recurrence->occurrencesIn(CarbonImmutable::parse("{$month}-01")),
    );
}

it('gera uma ocorrencia mensal no dia escolhido', function (): void {
    $recurrence = makeRecurrence(['day_of_month' => 28]);

    expect(datesIn($recurrence, '2026-08'))->toBe(['2026-08-28']);
});

it('ajusta o dia para meses curtos', function (): void {
    $recurrence = makeRecurrence(['day_of_month' => 28, 'starts_on' => '2026-01-28']);

    // Fevereiro de 2027 tem 28 dias; o dia 28 continua existindo.
    expect(datesIn($recurrence, '2027-02'))->toBe(['2027-02-28']);
});

it('respeita o intervalo de meses', function (): void {
    $recurrence = makeRecurrence([
        'day_of_month' => 5,
        'interval' => 2,
        'starts_on' => '2026-01-05',
    ]);

    expect(datesIn($recurrence, '2026-01'))->toBe(['2026-01-05'])
        ->and(datesIn($recurrence, '2026-02'))->toBe([])
        ->and(datesIn($recurrence, '2026-03'))->toBe(['2026-03-05'])
        ->and(datesIn($recurrence, '2026-04'))->toBe([]);
});

it('nao gera ocorrencia antes do inicio', function (): void {
    $recurrence = makeRecurrence(['day_of_month' => 10, 'starts_on' => '2026-06-15']);

    // O dia 10 de junho e anterior ao inicio: a primeira cai em julho.
    expect(datesIn($recurrence, '2026-06'))->toBe([])
        ->and(datesIn($recurrence, '2026-07'))->toBe(['2026-07-10']);
});

it('para no fim da vigencia', function (): void {
    $recurrence = makeRecurrence([
        'day_of_month' => 10,
        'starts_on' => '2026-01-10',
        'ends_on' => '2026-08-31',
    ]);

    expect(datesIn($recurrence, '2026-08'))->toBe(['2026-08-10'])
        ->and(datesIn($recurrence, '2026-09'))->toBe([]);
});

it('nao gera nada quando a regra esta pausada', function (): void {
    $recurrence = makeRecurrence(['day_of_month' => 10, 'is_active' => false]);

    expect(datesIn($recurrence, '2026-08'))->toBe([]);
});

it('gera varias ocorrencias numa regra semanal, mantendo o dia da semana', function (): void {
    $recurrence = makeRecurrence([
        'frequency' => RecurrenceFrequency::Semanal,
        'starts_on' => '2026-08-03', // uma segunda-feira
    ]);

    expect(datesIn($recurrence, '2026-08'))
        ->toBe(['2026-08-03', '2026-08-10', '2026-08-17', '2026-08-24', '2026-08-31']);
});

it('gera a serie diaria do mes inteiro', function (): void {
    $recurrence = makeRecurrence([
        'frequency' => RecurrenceFrequency::Diaria,
        'starts_on' => '2026-01-01',
    ]);

    expect(datesIn($recurrence, '2026-08'))->toHaveCount(31);
});

it('salta ciclos sem percorrer um a um numa regra diaria antiga', function (): void {
    $recurrence = makeRecurrence([
        'frequency' => RecurrenceFrequency::Diaria,
        'interval' => 3,
        'starts_on' => '2020-01-01',
    ]);

    $dates = datesIn($recurrence, '2026-08');

    // A cada 3 dias desde 01/01/2020 a serie cai em 31/07 e so entao em 03/08:
    // o alinhamento vem do inicio da regra, nao do comeco do mes.
    expect($dates)->toHaveCount(10)
        ->and($dates[0])->toBe('2026-08-03')
        ->and($dates[1])->toBe('2026-08-06')
        ->and($dates[9])->toBe('2026-08-30');
});

it('gera a regra anual so no mes de aniversario', function (): void {
    $recurrence = makeRecurrence([
        'frequency' => RecurrenceFrequency::Anual,
        'starts_on' => '2026-03-14',
    ]);

    expect(datesIn($recurrence, '2027-03'))->toBe(['2027-03-14'])
        ->and(datesIn($recurrence, '2027-04'))->toBe([]);
});
