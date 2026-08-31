<?php

declare(strict_types=1);

namespace App\Domain\Import\Queries;

use App\Domain\Import\DTOs\ImportSource;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Transaction;
use Carbon\CarbonImmutable;

/**
 * O que ja existe no destino dentro do periodo do arquivo, agrupado por
 * data + valor + direcao.
 *
 * Vem em uma consulta so, agregada no banco: conferir linha a linha faria uma
 * ida ao banco por lancamento do extrato — centenas em um arquivo de meses.
 * A contagem importa porque duas compras iguais no mesmo dia sao comuns: com
 * uma no banco e duas no arquivo, so a primeira e repetida.
 */
final class ExistingEntriesQuery
{
    /** @return array<string, int> chave "data|valor|direcao" => quantidade ja registrada */
    public function handle(ImportSource $source, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Transaction::query()
            ->ownedBy($source->userId)
            ->when(
                $source->accountId !== null,
                // Em conta, a compra-mae de cartao nao aparece: ela pertence
                // ao cartao, nao ao extrato bancario.
                fn ($query) => $query->where('account_id', $source->accountId)
                    ->where('is_installment_parent', false),
                // Em cartao, o que representa a compra e justamente a mae.
                fn ($query) => $query->where('credit_card_id', $source->creditCardId)
                    ->where('is_installment_parent', true),
            )
            ->whereBetween('competence_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', TransactionStatus::Cancelado->value)
            ->selectRaw('competence_date, amount, direction, COUNT(*) AS occurrences')
            ->groupBy('competence_date', 'amount', 'direction')
            ->toBase()
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $key = sprintf(
                '%s|%.2f|%s',
                CarbonImmutable::parse((string) $row->competence_date)->toDateString(),
                (float) $row->amount,
                (string) $row->direction,
            );

            $counts[$key] = (int) $row->occurrences;
        }

        return $counts;
    }
}
