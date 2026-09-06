<?php

declare(strict_types=1);

namespace App\Domain\Banking\Exceptions;

use App\Support\Exceptions\DomainRuleException;

/**
 * Excluir uma conta apaga em cascata os lancamentos dela: meses de extrato,
 * transferencias e pagamentos de fatura sairiam sem aviso. Conta usada se
 * arquiva; excluir fica so para a que nunca recebeu movimento.
 */
final class AccountNotRemovableException extends DomainRuleException
{
    public static function hasMovements(int $movements): self
    {
        $label = $movements === 1 ? 'um lançamento' : "{$movements} lançamentos";

        return new self(
            "Esta conta tem {$label} no extrato. Arquive-a em vez de excluir, ".
            'para não apagar o histórico já registrado.',
        );
    }

    public static function isCardPaymentAccount(int $cards): self
    {
        return new self($cards === 1
            ? 'Um cartão paga a fatura por esta conta. Aponte outra conta de pagamento nele '.
              'antes de excluí-la.'
            : "{$cards} cartões pagam a fatura por esta conta. Aponte outra conta de pagamento ".
              'neles antes de excluí-la.');
    }

    public static function isRecurrenceSource(int $recurrences): self
    {
        return new self($recurrences === 1
            ? 'Uma despesa fixa lança nesta conta. Mude a conta dessa regra antes de excluí-la, '.
              'senão ela ficaria sem destino.'
            : "{$recurrences} despesas fixas lançam nesta conta. Mude a conta dessas regras antes ".
              'de excluí-la, senão elas ficariam sem destino.');
    }

    public static function hasImports(int $batches): self
    {
        $label = $batches === 1 ? 'um extrato importado' : "{$batches} extratos importados";

        return new self(
            "Esta conta tem {$label} no histórico de importação. Arquive-a em vez de excluir.",
        );
    }
}
