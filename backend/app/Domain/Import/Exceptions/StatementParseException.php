<?php

declare(strict_types=1);

namespace App\Domain\Import\Exceptions;

use App\Support\Exceptions\DomainRuleException;

/**
 * O arquivo chegou inteiro, mas nao da para ler o que ha dentro dele.
 *
 * E 422 e nao 500: nada quebrou no servidor: o usuario mandou um arquivo que
 * o parser nao reconhece, e a mensagem precisa dizer o que fazer a respeito.
 */
final class StatementParseException extends DomainRuleException
{
    public static function emptyFile(): self
    {
        return new self('O arquivo está vazio.');
    }

    public static function unsupportedExtension(string $extension): self
    {
        $label = $extension === '' ? 'sem extensão' : ".{$extension}";

        return new self(
            "Não sabemos ler um arquivo {$label}. Envie o extrato em OFX ou CSV — ".
            'os dois saem do internet banking.',
        );
    }

    public static function noTransactions(): self
    {
        return new self(
            'Não encontramos nenhum lançamento no arquivo. '.
            'Confira se o extrato exportado cobre algum período com movimento.',
        );
    }

    public static function notOfx(): self
    {
        return new self(
            'Este arquivo não parece um OFX: não encontramos a marcação de lançamentos. '.
            'Se ele for uma planilha, salve como CSV e envie de novo.',
        );
    }

    /** @param  list<string>  $headers */
    public static function undetectableCsvColumns(array $headers): self
    {
        $found = $headers === []
            ? 'o arquivo não tem cabeçalho'
            : 'encontramos: '.implode(', ', array_slice($headers, 0, 8));

        return new self(
            'Não identificamos as colunas de data, descrição e valor neste CSV — '.
            "{$found}. Escolha as colunas na tela para continuar.",
        );
    }

    public static function tooManyRows(int $limit): self
    {
        return new self(
            "O arquivo tem mais de {$limit} lançamentos. ".
            'Exporte o extrato em períodos menores e importe um de cada vez.',
        );
    }
}
