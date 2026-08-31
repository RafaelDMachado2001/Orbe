<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

/**
 * O que o usuario esta lancando. Cada caso corresponde a uma aba do formulario
 * e a exatamente uma Action de escrita.
 *
 * Nao se confunde com TransactionType: uma despesa em conta, uma compra no
 * cartao e uma parcela de emprestimo sao todas do tipo despesa, mas nascem por
 * caminhos diferentes — a do cartao vira compra-mae mais parcelas de fatura, a
 * do emprestimo vira N lancamentos de conta.
 */
enum EntryKind: string
{
    case Receita = 'receita';
    case Despesa = 'despesa';
    case Cartao = 'cartao';
    case Emprestimo = 'emprestimo';
    case Transferencia = 'transferencia';

    public function label(): string
    {
        return match ($this) {
            self::Receita => 'Receita',
            self::Despesa => 'Despesa',
            self::Cartao => 'Compra no cartão',
            self::Emprestimo => 'Empréstimo',
            self::Transferencia => 'Transferência',
        };
    }

    public function transactionType(): TransactionType
    {
        return match ($this) {
            self::Receita => TransactionType::Receita,
            self::Despesa, self::Cartao, self::Emprestimo => TransactionType::Despesa,
            self::Transferencia => TransactionType::Transferencia,
        };
    }

    /** Categoria aceita neste lancamento; transferencia nao classifica. */
    public function categoryType(): ?CategoryType
    {
        return match ($this) {
            self::Receita => CategoryType::Receita,
            self::Despesa, self::Cartao, self::Emprestimo => CategoryType::Despesa,
            self::Transferencia => null,
        };
    }

    /** Lancamento em conta que aceita ser dividido em N meses. */
    public function acceptsInstallmentPlan(): bool
    {
        return match ($this) {
            self::Despesa, self::Emprestimo => true,
            self::Receita, self::Cartao, self::Transferencia => false,
        };
    }

    /** Deduz a aba a partir de um lancamento ja gravado, para a edicao. */
    public static function forTransaction(
        TransactionType $type,
        bool $isCardPurchase,
        bool $isLoan = false,
    ): self {
        if ($isCardPurchase) {
            return self::Cartao;
        }

        if ($isLoan) {
            return self::Emprestimo;
        }

        return match ($type) {
            TransactionType::Receita => self::Receita,
            TransactionType::Despesa => self::Despesa,
            TransactionType::Transferencia => self::Transferencia,
        };
    }
}
