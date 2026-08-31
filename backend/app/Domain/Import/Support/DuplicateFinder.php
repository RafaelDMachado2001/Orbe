<?php

declare(strict_types=1);

namespace App\Domain\Import\Support;

use App\Domain\Import\DTOs\ParsedEntry;

/**
 * Decide se uma linha do arquivo ja existe no destino.
 *
 * A comparacao e por data, valor e direcao — nao pela descricao, que o banco
 * reescreve entre um export e outro ("PIX ENVIADO" vira "Pix enviado - Joao")
 * e que o usuario edita depois de lancar a mao.
 *
 * O identificador do OFX (FITID) seria mais exato, mas so o extrato tem: um
 * lancamento digitado na tela nunca teria um, e a maioria das repeticoes vem
 * justamente de importar por cima do que ja foi digitado.
 *
 * Cada correspondencia e consumida uma vez, entao duas linhas iguais no
 * arquivo contra uma no banco marcam so a primeira como repetida.
 */
final class DuplicateFinder
{
    /** @param  array<string, int>  $remaining */
    public function __construct(private array $remaining) {}

    public function consume(ParsedEntry $entry): bool
    {
        $key = sprintf('%s|%.2f|%s', $entry->date->toDateString(), $entry->amount, $entry->direction->value);

        if (($this->remaining[$key] ?? 0) < 1) {
            return false;
        }

        $this->remaining[$key]--;

        return true;
    }
}
