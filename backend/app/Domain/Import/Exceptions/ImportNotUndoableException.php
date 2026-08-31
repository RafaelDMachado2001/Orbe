<?php

declare(strict_types=1);

namespace App\Domain\Import\Exceptions;

use App\Support\Exceptions\DomainRuleException;

/**
 * Desfazer uma importacao apaga tudo o que ela criou, ou nao apaga nada.
 *
 * Uma parcela ja quitada junto com a fatura nao pode sumir: a fatura paga
 * passaria a divergir do valor que de fato saiu da conta. E remover so o resto
 * deixaria a importacao pela metade, sem que a tela soubesse dizer o que
 * sobrou — por isso a recusa e do lote inteiro.
 */
final class ImportNotUndoableException extends DomainRuleException
{
    public static function hasPaidInstallments(): self
    {
        return new self(
            'Esta importação tem compras que já foram pagas junto com a fatura. '.
            'Desfazê-la faria a fatura paga divergir do que saiu da conta — '.
            'exclua os lançamentos que ainda quiser remover pela tela de Lançamentos.',
        );
    }

    public static function hasInvoicePayments(): self
    {
        return new self(
            'Esta importação gerou uma baixa de fatura, que só pode ser desfeita '.
            'pela tela de Cartões.',
        );
    }
}
