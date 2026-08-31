<?php

declare(strict_types=1);

namespace App\Domain\Import\Enums;

enum ImportFormat: string
{
    case Ofx = 'ofx';
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Ofx => 'OFX',
            self::Csv => 'CSV',
        };
    }

    /**
     * Deduz o formato pela extensao do arquivo enviado.
     *
     * OFX de banco brasileiro as vezes chega com extensao .txt (o conteudo e
     * SGML mesmo assim), entao .txt cai em OFX e nao em CSV.
     */
    public static function fromExtension(string $extension): ?self
    {
        return match (mb_strtolower($extension)) {
            'ofx', 'qfx', 'txt' => self::Ofx,
            'csv' => self::Csv,
            default => null,
        };
    }

    /** @return list<string> */
    public static function acceptedExtensions(): array
    {
        return ['ofx', 'qfx', 'txt', 'csv'];
    }
}
