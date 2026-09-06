<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

/**
 * Categorias que o proprio sistema precisa localizar, independentemente do
 * nome que o usuario deu a elas.
 */
enum SystemCategory: string
{
    case Emprestimos = 'emprestimos';
    case AjusteEntrada = 'ajuste_entrada';
    case AjusteSaida = 'ajuste_saida';

    public function defaultName(): string
    {
        return match ($this) {
            self::Emprestimos => 'Empréstimos e financiamentos',
            self::AjusteEntrada, self::AjusteSaida => 'Ajuste de saldo',
        };
    }

    /**
     * O ajuste de saldo precisa de uma categoria de cada lado: conciliar para
     * cima e uma entrada, para baixo e uma saida, e o tipo da categoria e o
     * que o resto do app usa para separar receita de despesa.
     */
    public static function adjustmentFor(TransactionType $type): self
    {
        return $type === TransactionType::Receita ? self::AjusteEntrada : self::AjusteSaida;
    }

    public function type(): CategoryType
    {
        return match ($this) {
            self::AjusteEntrada => CategoryType::Receita,
            self::Emprestimos, self::AjusteSaida => CategoryType::Despesa,
        };
    }
}
