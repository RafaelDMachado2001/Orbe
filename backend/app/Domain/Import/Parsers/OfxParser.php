<?php

declare(strict_types=1);

namespace App\Domain\Import\Parsers;

use App\Domain\Import\DTOs\ParsedEntry;
use App\Domain\Import\DTOs\ParsedStatement;
use App\Domain\Import\Exceptions\StatementParseException;
use App\Domain\Import\Support\AmountReader;
use App\Domain\Import\Support\DateReader;
use App\Domain\Ledger\Enums\MovementDirection;

/**
 * Le o extrato OFX que o internet banking exporta.
 *
 * O padrao existe em duas encarnacoes: OFX 1.x e SGML (tags de valor sem
 * fechamento) e OFX 2.x e XML bem formado. Como as duas usam os mesmos nomes
 * de tag e fecham os agregados, uma leitura por marcacao atende as duas — um
 * parser XML rejeitaria de cara a versao SGML, que e justamente a que os
 * bancos brasileiros entregam.
 *
 * Extrato de conta (`STMTRS`) e fatura de cartao (`CCSTMTRS`) tem a mesma
 * estrutura de lancamento, entao nao precisam de leituras separadas.
 */
final class OfxParser
{
    /** Teto de linhas por arquivo, para um extrato de anos nao derrubar a requisicao. */
    public const MAX_ROWS = 2000;

    public function parse(string $contents): ParsedStatement
    {
        $body = $this->toUtf8($contents);

        if (trim($body) === '') {
            throw StatementParseException::emptyFile();
        }

        if (preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/is', $body, $matches) === 0) {
            throw StatementParseException::notOfx();
        }

        /** @var list<string> $blocks */
        $blocks = $matches[1];

        if (count($blocks) > self::MAX_ROWS) {
            throw StatementParseException::tooManyRows(self::MAX_ROWS);
        }

        $entries = [];

        foreach ($blocks as $block) {
            $entry = $this->toEntry($block);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        if ($entries === []) {
            throw StatementParseException::noTransactions();
        }

        usort($entries, static fn (ParsedEntry $a, ParsedEntry $b): int => $a->date <=> $b->date);

        return new ParsedStatement(entries: $entries);
    }

    private function toEntry(string $block): ?ParsedEntry
    {
        $date = DateReader::parse($this->tag($block, 'DTPOSTED') ?? $this->tag($block, 'DTUSER') ?? '');
        $amount = AmountReader::parse($this->tag($block, 'TRNAMT') ?? '');

        // Linha sem data ou sem valor nao e um lancamento; e ruido de um
        // exportador incompleto. Zerada tambem nao move dinheiro.
        if ($date === null || $amount === null || abs($amount) < 0.005) {
            return null;
        }

        return new ParsedEntry(
            date: $date,
            description: $this->description($block),
            amount: abs($amount),
            direction: $this->direction($block, $amount),
            externalId: $this->tag($block, 'FITID'),
        );
    }

    /**
     * O sinal de TRNAMT e a fonte da verdade — TRNTYPE so entra quando o valor
     * veio sem sinal, o que acontece em exportadores que confiam no tipo.
     */
    private function direction(string $block, float $amount): MovementDirection
    {
        if ($amount < 0) {
            return MovementDirection::Saida;
        }

        $type = mb_strtoupper($this->tag($block, 'TRNTYPE') ?? '');

        return in_array($type, ['DEBIT', 'PAYMENT', 'FEE', 'SRVCHG', 'ATM', 'CHECK', 'DIRECTDEBIT'], true)
            ? MovementDirection::Saida
            : MovementDirection::Entrada;
    }

    /**
     * MEMO e o texto que o banco mostra no extrato; NAME e o do beneficiario.
     * Sem nenhum dos dois, o tipo da transacao ainda diz mais que uma linha
     * em branco.
     */
    private function description(string $block): string
    {
        $description = $this->tag($block, 'MEMO')
            ?? $this->tag($block, 'NAME')
            ?? $this->tag($block, 'TRNTYPE')
            ?? 'Lançamento importado';

        // Extratos vem em caixa alta e com espaco duplo do alinhamento fixo.
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $description)), 0, 255);
    }

    /** Primeiro valor de uma tag, funcionando em SGML (sem fechamento) e em XML. */
    private function tag(string $block, string $tag): ?string
    {
        if (preg_match('/<'.$tag.'>([^<\r\n]*)/i', $block, $match) !== 1) {
            return null;
        }

        $value = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value === '' ? null : $value;
    }

    /**
     * Banco brasileiro costuma exportar OFX em Windows-1252 (o cabecalho diz
     * CHARSET:1252). Gravar isso como se fosse UTF-8 corromperia todo acento
     * da descricao, entao a conversao acontece antes de qualquer leitura.
     */
    private function toUtf8(string $contents): string
    {
        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        return mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }
}
