<?php

declare(strict_types=1);

namespace App\Domain\Import\DTOs;

use App\Domain\Import\Enums\ImportFormat;
use App\Domain\Import\Enums\ImportTarget;

/** O que a tela confirmou: destino, arquivo de origem e as linhas escolhidas. */
final readonly class ImportCommitData
{
    /** @param  list<ImportedRow>  $rows */
    public function __construct(
        public int $userId,
        public string $filename,
        public ImportFormat $format,
        public array $rows,
        public ?int $accountId = null,
        public ?int $creditCardId = null,
        /** Linhas que o usuario deixou de fora, guardadas so como estatistica do lote. */
        public int $skippedCount = 0,
    ) {
        if (($this->accountId === null) === ($this->creditCardId === null)) {
            throw new \InvalidArgumentException(
                'A importacao vai para uma conta ou para um cartao, nunca para os dois.',
            );
        }

        if ($this->rows === []) {
            throw new \InvalidArgumentException('Nenhuma linha foi selecionada para importar.');
        }
    }

    public function target(): ImportTarget
    {
        return $this->creditCardId === null ? ImportTarget::Conta : ImportTarget::Cartao;
    }
}
