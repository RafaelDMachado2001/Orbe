<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Cards\Models\CreditCard;

/**
 * Tira o cartao de circulacao sem apagar nada.
 *
 * O cartao arquivado some dos seletores de novo lancamento e da Visao geral,
 * mas as compras, parcelas e faturas continuam no extrato — arquivar e dizer
 * "nao uso mais", nao "nunca existiu". A mesma action reativa.
 */
final class ArchiveCreditCardAction
{
    public function handle(CreditCard $card, bool $isActive): CreditCard
    {
        $card->forceFill(['is_active' => $isActive])->save();

        return $card->refresh();
    }
}
