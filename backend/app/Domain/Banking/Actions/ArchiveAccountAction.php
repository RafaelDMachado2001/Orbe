<?php

declare(strict_types=1);

namespace App\Domain\Banking\Actions;

use App\Domain\Banking\Models\Account;

/**
 * Tira a conta de circulacao sem apagar nada.
 *
 * A conta arquivada sai dos seletores de lancamento, do saldo consolidado e da
 * Visao geral, mas o extrato dela continua inteiro — arquivar e dizer "encerrei
 * esta conta", nao "ela nunca existiu". A mesma action reativa.
 */
final class ArchiveAccountAction
{
    public function handle(Account $account, bool $isActive): Account
    {
        $account->forceFill(['is_active' => $isActive])->save();

        return $account->refresh();
    }
}
