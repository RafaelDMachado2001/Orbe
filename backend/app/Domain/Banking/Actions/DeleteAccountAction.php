<?php

declare(strict_types=1);

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\Exceptions\AccountNotRemovableException;
use App\Domain\Banking\Models\Account;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Import\Models\ImportBatch;
use App\Domain\Ledger\Models\Recurrence;
use App\Domain\Ledger\Models\Transaction;

/**
 * Exclui uma conta que nunca foi usada.
 *
 * Conta com lancamento fica de fora: o cascade do banco levaria o extrato
 * inteiro, e o outro lado de cada transferencia ficaria orfao. Para essas o
 * caminho e arquivar.
 *
 * As outras tres travas nao vem de cascade, e sim de referencia que ficaria
 * nula em silencio: um cartao que paga por esta conta, uma despesa fixa que
 * lanca nela e um lote de importacao que aponta para ela. Nos tres casos a
 * exclusao passaria, mas deixaria uma regra sem destino — e a pessoa
 * descobriria isso no mes seguinte.
 */
final class DeleteAccountAction
{
    public function handle(Account $account): void
    {
        $movements = Transaction::query()
            ->withoutUserScope()
            ->where('account_id', $account->id)
            ->count();

        if ($movements > 0) {
            throw AccountNotRemovableException::hasMovements($movements);
        }

        $cards = CreditCard::query()
            ->withoutUserScope()
            ->where('payment_account_id', $account->id)
            ->count();

        if ($cards > 0) {
            throw AccountNotRemovableException::isCardPaymentAccount($cards);
        }

        $recurrences = Recurrence::query()
            ->withoutUserScope()
            ->where('account_id', $account->id)
            ->count();

        if ($recurrences > 0) {
            throw AccountNotRemovableException::isRecurrenceSource($recurrences);
        }

        $batches = ImportBatch::query()
            ->withoutUserScope()
            ->where('account_id', $account->id)
            ->count();

        if ($batches > 0) {
            throw AccountNotRemovableException::hasImports($batches);
        }

        $account->delete();
    }
}
