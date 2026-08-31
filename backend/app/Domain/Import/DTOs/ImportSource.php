<?php

declare(strict_types=1);

namespace App\Domain\Import\DTOs;

use App\Domain\Import\Enums\ImportFormat;
use App\Domain\Import\Enums\ImportTarget;

/** O arquivo enviado e para onde ele vai. */
final readonly class ImportSource
{
    public function __construct(
        public int $userId,
        public string $contents,
        public string $filename,
        public ImportFormat $format,
        public ?int $accountId = null,
        public ?int $creditCardId = null,
        public ?CsvMapping $mapping = null,
    ) {
        if (($this->accountId === null) === ($this->creditCardId === null)) {
            throw new \InvalidArgumentException(
                'A importacao vai para uma conta ou para um cartao, nunca para os dois.',
            );
        }
    }

    public function target(): ImportTarget
    {
        return $this->creditCardId === null ? ImportTarget::Conta : ImportTarget::Cartao;
    }
}
