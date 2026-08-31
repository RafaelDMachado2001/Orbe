<?php

declare(strict_types=1);

namespace App\Domain\Ledger\DTOs;

use App\Domain\Ledger\Enums\MovementOrigin;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use Carbon\CarbonImmutable;

/**
 * Recorte pedido pela tela de Lancamentos.
 *
 * Uma lista vazia significa "sem filtro", nunca "nenhum" — assim a tela envia
 * so o que o usuario marcou, e o padrao continua sendo mostrar tudo.
 */
final readonly class TransactionFilters
{
    public const MAX_PER_PAGE = 100;

    /**
     * @param  list<TransactionType>  $types
     * @param  list<TransactionStatus>  $statuses
     * @param  list<MovementOrigin>  $origins
     * @param  list<int>  $categoryIds
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public array $types = [],
        public array $statuses = [],
        public array $origins = [],
        public array $categoryIds = [],
        public ?int $accountId = null,
        public ?int $creditCardId = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {
        if ($this->from->gt($this->to)) {
            throw new \InvalidArgumentException('A data inicial nao pode ser posterior a final.');
        }

        if ($this->page < 1) {
            throw new \InvalidArgumentException('A pagina precisa ser maior que zero.');
        }

        if ($this->perPage < 1 || $this->perPage > self::MAX_PER_PAGE) {
            throw new \InvalidArgumentException('Quantidade por pagina fora do intervalo permitido.');
        }
    }

    /**
     * Lancamentos de conta entram, a menos que o recorte aponte so para cartao.
     */
    public function includesAccountMovements(): bool
    {
        if ($this->creditCardId !== null) {
            return false;
        }

        return $this->origins === [] || in_array(MovementOrigin::Conta, $this->origins, true);
    }

    /**
     * Parcelas de cartao entram, a menos que o recorte aponte so para conta.
     * Cartao so produz despesa: filtrar por receita ou transferencia elimina
     * o ramo inteiro em vez de devolver linhas que nunca casariam.
     */
    public function includesCardMovements(): bool
    {
        if ($this->accountId !== null) {
            return false;
        }

        if ($this->types !== [] && ! in_array(TransactionType::Despesa, $this->types, true)) {
            return false;
        }

        return $this->origins === [] || in_array(MovementOrigin::Cartao, $this->origins, true);
    }

    /** @return list<string> */
    public function statusValues(): array
    {
        return array_map(static fn (TransactionStatus $status): string => $status->value, $this->statuses);
    }

    /** @return list<string> */
    public function typeValues(): array
    {
        return array_map(static fn (TransactionType $type): string => $type->value, $this->types);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
