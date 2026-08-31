<?php

declare(strict_types=1);

namespace App\Domain\Cards\Actions;

use App\Domain\Cards\Exceptions\CreditCardNotRemovableException;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Exclui um cartao que nunca foi usado.
 *
 * Cartao com compra fica de fora: o cascade do banco levaria transacoes,
 * parcelas e faturas junto, e o extrato perderia meses de historico sem que
 * ninguem pedisse isso. Para esses, o caminho e arquivar.
 */
final class DeleteCreditCardAction
{
    public function handle(CreditCard $card): void
    {
        $purchases = Transaction::query()
            ->withoutUserScope()
            ->where('credit_card_id', $card->id)
            ->count();

        if ($purchases > 0) {
            throw CreditCardNotRemovableException::hasMovements($purchases);
        }

        DB::transaction(function () use ($card): void {
            // Faturas vazias podem existir sem compra alguma; elas saem junto.
            $card->invoices()->withoutUserScope()->delete();
            $card->delete();
        });
    }
}
