<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Cards\Actions\RegisterCardPurchaseAction;
use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Ledger\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Transforma um mes de uma regra fixa em lancamento de verdade.
 *
 * Cria uma linha por ocorrencia da regra dentro do mes — uma para a mensal,
 * quatro ou cinco para a semanal. Regra no cartao vira compra de uma parcela
 * na fatura correspondente, e nao debito em conta: o dinheiro so sai quando a
 * fatura for paga.
 *
 * **Nao lanca duas vezes a mesma data.** A verificacao olha os lancamentos que
 * ja existem para aquela regra naquele dia, e nao o campo
 * last_materialized_on: se o usuario apagar o lancamento gerado, ele precisa
 * poder lancar de novo, e um carimbo de data nao saberia disso. O campo segue
 * preenchido como registro de quando a regra rodou pela ultima vez.
 */
final class MaterializeRecurrenceAction
{
    public function __construct(
        private readonly RecordTransactionAction $record,
        private readonly RegisterCardPurchaseAction $registerPurchase,
    ) {}

    /** @return list<Transaction> lancamentos criados agora */
    public function handle(Recurrence $recurrence, CarbonImmutable $month, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now();
        $pending = $this->pendingDates($recurrence, $month);

        if ($pending === []) {
            return [];
        }

        return DB::transaction(function () use ($recurrence, $pending, $today): array {
            $created = [];

            foreach ($pending as $date) {
                $created[] = $this->createFor($recurrence, $date, $today);
            }

            $recurrence->forceFill([
                'last_materialized_on' => end($pending)->toDateString(),
            ])->save();

            return $created;
        });
    }

    /**
     * Datas da regra nesse mes que ainda nao viraram lancamento.
     *
     * @return list<CarbonImmutable>
     */
    public function pendingDates(Recurrence $recurrence, CarbonImmutable $month): array
    {
        $occurrences = $recurrence->occurrencesIn($month);

        if ($occurrences === []) {
            return [];
        }

        $existing = Transaction::query()
            ->withoutUserScope()
            ->where('recurrence_id', $recurrence->id)
            ->whereIn('competence_date', array_map(
                static fn (CarbonImmutable $date): string => $date->toDateString(),
                $occurrences,
            ))
            ->pluck('competence_date')
            ->map(static fn (mixed $date): string => CarbonImmutable::parse((string) $date)->toDateString())
            ->all();

        return array_values(array_filter(
            $occurrences,
            static fn (CarbonImmutable $date): bool => ! in_array($date->toDateString(), $existing, true),
        ));
    }

    private function createFor(
        Recurrence $recurrence,
        CarbonImmutable $date,
        CarbonImmutable $today,
    ): Transaction {
        $status = $this->statusFor($date, $today);

        if ($recurrence->credit_card_id !== null) {
            return $this->registerPurchase->handle(new CardPurchaseData(
                creditCardId: $recurrence->credit_card_id,
                description: $recurrence->description,
                amount: (float) $recurrence->amount,
                purchaseDate: $date,
                installments: 1,
                categoryId: $recurrence->category_id,
                status: $status,
                recurrenceId: $recurrence->id,
            ));
        }

        return $this->record->handle(new TransactionData(
            accountId: (int) $recurrence->account_id,
            description: $recurrence->description,
            amount: (float) $recurrence->amount,
            type: $recurrence->type,
            competenceDate: $date,
            categoryId: $recurrence->category_id,
            status: $status,
            method: $recurrence->method,
            paidDate: $status === TransactionStatus::Confirmado ? $date : null,
            recurrenceId: $recurrence->id,
        ));
    }

    /**
     * Ocorrencia que ja passou nasce confirmada; a que ainda vai acontecer
     * nasce prevista, para nao alterar o saldo antes da hora.
     */
    private function statusFor(CarbonImmutable $date, CarbonImmutable $today): TransactionStatus
    {
        return $date->startOfDay()->lte($today->startOfDay())
            ? TransactionStatus::Confirmado
            : TransactionStatus::Previsto;
    }
}
