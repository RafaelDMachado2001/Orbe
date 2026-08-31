<?php

declare(strict_types=1);

namespace App\Domain\Import\Support;

use App\Domain\Import\DTOs\CsvMapping;
use App\Domain\Import\DTOs\ParsedStatement;
use App\Domain\Import\Enums\ImportFormat;
use App\Domain\Import\Parsers\CsvParser;
use App\Domain\Import\Parsers\OfxParser;

/** Escolhe o parser pelo formato. Quem chama nao precisa saber qual e. */
final class StatementReader
{
    public function __construct(
        private readonly OfxParser $ofx,
        private readonly CsvParser $csv,
    ) {}

    public function read(string $contents, ImportFormat $format, ?CsvMapping $mapping = null): ParsedStatement
    {
        return match ($format) {
            ImportFormat::Ofx => $this->ofx->parse($contents),
            ImportFormat::Csv => $this->csv->parse($contents, $mapping),
        };
    }
}
