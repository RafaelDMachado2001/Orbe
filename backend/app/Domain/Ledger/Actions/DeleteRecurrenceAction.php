<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Models\Recurrence;

/**
 * Exclui a regra sem tocar nos lancamentos que ela ja gerou.
 *
 * A chave estrangeira em transactions e nullOnDelete: os lancamentos passados
 * permanecem no extrato, apenas deixam de exibir o selo de recorrente. Apagar
 * meses de historico porque a assinatura foi cancelada seria destruir o
 * registro do que de fato aconteceu.
 */
final class DeleteRecurrenceAction
{
    public function handle(Recurrence $recurrence): void
    {
        $recurrence->delete();
    }
}
