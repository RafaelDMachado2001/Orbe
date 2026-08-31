<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Ledger\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Lanca de uma vez todas as regras ativas com pendencia no mes.
 *
 * Existe porque o caso real e "virou o mes, quero as sete contas fixas no
 * extrato" — fazer isso em sete cliques so testaria a paciencia. Uma regra sem
 * pendencia e simplesmente pulada, entao repetir a acao no mesmo mes nao
 * duplica nada.
 */
final class MaterializeDueRecurrencesAction
{
    public function __construct(
        private readonly MaterializeRecurrenceAction $materialize,
    ) {}

    /** @return list<Transaction> */
    public function handle(int $userId, CarbonImmutable $month, ?CarbonImmutable $today = null): array
    {
        $recurrences = Recurrence::query()
            ->ownedBy($userId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return DB::transaction(function () use ($recurrences, $month, $today): array {
            $created = [];

            foreach ($recurrences as $recurrence) {
                $created = [...$created, ...$this->materialize->handle($recurrence, $month, $today)];
            }

            return $created;
        });
    }
}
