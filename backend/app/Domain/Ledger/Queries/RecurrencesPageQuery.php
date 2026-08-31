<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Queries;

use App\Domain\Ledger\Actions\MaterializeRecurrenceAction;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Recurrence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * As regras fixas de um mes, com o estado de cada uma naquele mes.
 *
 * O que a tela precisa saber por regra nao e so "quanto" e "quando", mas
 * "ja lancei?" — e essa resposta vem de contar os lancamentos existentes com o
 * vinculo da regra nas datas do mes, numa consulta so para todas as regras.
 *
 * @phpstan-type RecurrenceRow array{
 *     id: int,
 *     description: string,
 *     amount: float,
 *     monthly_amount: float,
 *     type: string,
 *     frequency: string,
 *     frequency_label: string,
 *     interval: int,
 *     day_of_month: int|null,
 *     schedule_label: string,
 *     starts_on: string,
 *     ends_on: string|null,
 *     last_materialized_on: string|null,
 *     is_active: bool,
 *     source: string,
 *     source_kind: string,
 *     account_id: int|null,
 *     credit_card_id: int|null,
 *     method: string|null,
 *     category: array{id: int, name: string, color: string}|null,
 *     occurrences: int,
 *     launched: int,
 *     pending: int,
 *     next_date: string|null
 * }
 */
final class RecurrencesPageQuery
{
    public function __construct(
        private readonly MaterializeRecurrenceAction $materialize,
    ) {}

    /**
     * @return array{summary: array<string, mixed>, recurrences: list<RecurrenceRow>}
     */
    public function handle(int $userId, CarbonImmutable $month, bool $includePaused = true): array
    {
        $recurrences = Recurrence::query()
            ->ownedBy($userId)
            ->with(['category', 'account.bank', 'creditCard'])
            ->when(! $includePaused, fn ($query) => $query->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderBy('type')
            ->orderBy('description')
            ->get();

        $launchedByRecurrence = $this->launchedCountByRecurrence($userId, $month);

        $rows = $recurrences
            ->map(fn (Recurrence $recurrence): array => $this->present(
                $recurrence,
                $month,
                (int) ($launchedByRecurrence[$recurrence->id] ?? 0),
            ))
            ->all();

        return ['summary' => $this->summarize($rows), 'recurrences' => $rows];
    }

    /**
     * @param  list<RecurrenceRow>  $rows
     * @return array<string, mixed>
     */
    private function summarize(array $rows): array
    {
        $active = array_values(array_filter($rows, static fn (array $row): bool => $row['is_active']));

        $sumOf = static fn (string $type): float => round(array_sum(array_map(
            static fn (array $row): float => $row['type'] === $type ? $row['monthly_amount'] : 0.0,
            $active,
        )), 2);

        $expense = $sumOf(TransactionType::Despesa->value);
        $income = $sumOf(TransactionType::Receita->value);

        return [
            'expense_total' => $expense,
            'income_total' => $income,
            'net_total' => round($income - $expense, 2),
            'active_count' => count($active),
            'paused_count' => count($rows) - count($active),
            'pending_count' => array_sum(array_column($active, 'pending')),
            'pending_amount' => round(array_sum(array_map(
                static fn (array $row): float => $row['type'] === TransactionType::Despesa->value
                    ? $row['pending'] * $row['amount']
                    : 0.0,
                $active,
            )), 2),
        ];
    }

    /**
     * Quantos lancamentos cada regra ja tem dentro do mes. Uma consulta
     * agrupada para todas as regras, em vez de uma por linha da tela.
     *
     * @return array<int, int>
     */
    private function launchedCountByRecurrence(int $userId, CarbonImmutable $month): array
    {
        return DB::table('transactions')
            ->selectRaw(<<<'SQL'
                recurrence_id      AS recurrence_id,
                       COUNT(1)    AS total
                SQL)
            ->where('user_id', $userId)
            ->whereNotNull('recurrence_id')
            ->whereBetween('competence_date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->groupBy('recurrence_id')
            ->pluck('total', 'recurrence_id')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }

    /** @return RecurrenceRow */
    private function present(Recurrence $recurrence, CarbonImmutable $month, int $launched): array
    {
        $occurrences = $recurrence->occurrencesIn($month);
        $pending = $this->materialize->pendingDates($recurrence, $month);
        $amount = round((float) $recurrence->amount, 2);

        return [
            'id' => $recurrence->id,
            'description' => $recurrence->description,
            'amount' => $amount,
            // Valor equivalente por mes, para somar regras de frequencias
            // diferentes no painel sem comparar laranja com maca.
            'monthly_amount' => round(
                $amount * $recurrence->frequency->occurrencesPerMonth($recurrence->interval),
                2,
            ),
            'type' => $recurrence->type->value,
            'frequency' => $recurrence->frequency->value,
            'frequency_label' => $recurrence->frequency->label(),
            'interval' => $recurrence->interval,
            'day_of_month' => $recurrence->day_of_month,
            'schedule_label' => $this->scheduleLabel($recurrence),
            'starts_on' => $recurrence->starts_on->toDateString(),
            'ends_on' => $recurrence->ends_on?->toDateString(),
            'last_materialized_on' => $recurrence->last_materialized_on?->toDateString(),
            'is_active' => $recurrence->is_active,
            'source' => $this->source($recurrence),
            'source_kind' => $recurrence->credit_card_id === null ? 'conta' : 'cartao',
            'account_id' => $recurrence->account_id,
            'credit_card_id' => $recurrence->credit_card_id,
            'method' => $recurrence->method?->value,
            'category' => $recurrence->category === null ? null : [
                'id' => $recurrence->category->id,
                'name' => $recurrence->category->name,
                'color' => $recurrence->category->color,
            ],
            'occurrences' => count($occurrences),
            'launched' => $launched,
            'pending' => count($pending),
            'next_date' => ($pending[0] ?? $occurrences[0] ?? null)?->toDateString(),
        ];
    }

    private function source(Recurrence $recurrence): string
    {
        if ($recurrence->creditCard !== null) {
            return $recurrence->creditCard->nickname;
        }

        $account = $recurrence->account;

        return $account === null ? 'Conta' : $account->nickname;
    }

    /** "Todo dia 28", "A cada 2 semanas", "Todo ano em março". */
    private function scheduleLabel(Recurrence $recurrence): string
    {
        $interval = max($recurrence->interval, 1);
        $unit = match ($recurrence->frequency->value) {
            'diaria' => $interval === 1 ? 'dia' : 'dias',
            'semanal' => $interval === 1 ? 'semana' : 'semanas',
            'anual' => $interval === 1 ? 'ano' : 'anos',
            default => $interval === 1 ? 'mês' : 'meses',
        };

        if ($interval > 1) {
            return "A cada {$interval} {$unit}";
        }

        return match ($recurrence->frequency->value) {
            'diaria' => 'Todo dia',
            'semanal' => 'Toda '.$recurrence->starts_on->translatedFormat('l'),
            'anual' => 'Todo ano em '.$recurrence->starts_on->translatedFormat('F'),
            default => $recurrence->day_of_month === null
                ? 'Todo mês'
                : "Todo dia {$recurrence->day_of_month}",
        };
    }
}
