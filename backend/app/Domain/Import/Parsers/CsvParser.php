<?php

declare(strict_types=1);

namespace App\Domain\Import\Parsers;

use App\Domain\Import\DTOs\CsvMapping;
use App\Domain\Import\DTOs\ParsedEntry;
use App\Domain\Import\DTOs\ParsedStatement;
use App\Domain\Import\Exceptions\StatementParseException;
use App\Domain\Import\Support\AmountReader;
use App\Domain\Import\Support\DateReader;
use App\Domain\Ledger\Enums\MovementDirection;
use Illuminate\Support\Str;

/**
 * Le o extrato em CSV.
 *
 * Ao contrario do OFX, CSV nao tem padrao: muda o separador, a ordem das
 * colunas e ate se o valor vem em uma coluna com sinal ou em duas separando
 * debito de credito. O parser tenta adivinhar tudo isso e devolve o
 * mapeamento junto com as linhas, para a tela mostrar o palpite e deixar
 * corrigir — adivinhar em silencio importaria o extrato trocado.
 */
final class CsvParser
{
    public const MAX_ROWS = 2000;

    /** Fracao das linhas que precisa bater para uma coluna ser aceita como data. */
    private const DETECTION_THRESHOLD = 0.6;

    public function parse(string $contents, ?CsvMapping $mapping = null): ParsedStatement
    {
        $body = $this->toUtf8($contents);

        if (trim($body) === '') {
            throw StatementParseException::emptyFile();
        }

        $delimiter = $mapping->delimiter ?? $this->detectDelimiter($body);
        $rows = $this->readRows($body, $delimiter);

        if ($rows === []) {
            throw StatementParseException::emptyFile();
        }

        $hasHeader = $mapping->hasHeader ?? ! $this->looksLikeData($rows[0]);
        $headers = $hasHeader ? array_map(trim(...), $rows[0]) : [];
        $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;

        if (count($dataRows) > self::MAX_ROWS) {
            throw StatementParseException::tooManyRows(self::MAX_ROWS);
        }

        $mapping ??= $this->detectMapping($headers, $dataRows, $delimiter, $hasHeader);

        $entries = [];

        foreach ($dataRows as $row) {
            $entry = $this->toEntry($row, $mapping);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        if ($entries === []) {
            throw StatementParseException::noTransactions();
        }

        usort($entries, static fn (ParsedEntry $a, ParsedEntry $b): int => $a->date <=> $b->date);

        return new ParsedStatement(
            entries: $entries,
            mapping: $mapping,
            headers: $headers,
            columns: max(array_map('count', $rows)),
        );
    }

    /**
     * @param  list<string>  $row
     */
    private function toEntry(array $row, CsvMapping $mapping): ?ParsedEntry
    {
        $date = DateReader::parse($this->cell($row, $mapping->dateColumn));

        // Linha sem data e rodape de saldo, cabecalho repetido ou separador de
        // secao — coisas que todo exportador de banco intercala no arquivo.
        if ($date === null) {
            return null;
        }

        $amount = $this->amount($row, $mapping);

        if ($amount === null || abs($amount) < 0.005) {
            return null;
        }

        $description = trim((string) preg_replace('/\s+/u', ' ', $this->cell($row, $mapping->descriptionColumn)));

        return new ParsedEntry(
            date: $date,
            description: mb_substr($description === '' ? 'Lançamento importado' : $description, 0, 255),
            amount: abs($amount),
            direction: $amount < 0 ? MovementDirection::Saida : MovementDirection::Entrada,
        );
    }

    /**
     * Valor com sinal da linha. Nas duas formas de arquivo o resultado e o
     * mesmo: negativo saiu, positivo entrou.
     *
     * @param  list<string>  $row
     */
    private function amount(array $row, CsvMapping $mapping): ?float
    {
        $primary = AmountReader::parse($this->cell($row, $mapping->amountColumn));

        if (! $mapping->isSplitAmount()) {
            return $primary;
        }

        $inflow = AmountReader::parse($this->cell($row, (int) $mapping->inflowColumn));

        if ($inflow !== null && abs($inflow) >= 0.005) {
            return abs($inflow);
        }

        return $primary === null ? null : -abs($primary);
    }

    /** @param  list<string>  $row */
    private function cell(array $row, int $index): string
    {
        return trim($row[$index] ?? '');
    }

    /**
     * Palpite de mapeamento: pelo cabecalho quando ele existe, pelo conteudo
     * das colunas quando o arquivo nao tem cabecalho.
     *
     * @param  list<string>  $headers
     * @param  list<list<string>>  $dataRows
     */
    private function detectMapping(array $headers, array $dataRows, string $delimiter, bool $hasHeader): CsvMapping
    {
        $detected = $headers === []
            ? $this->detectFromContent($dataRows)
            : ($this->detectFromHeaders($headers) ?? $this->detectFromContent($dataRows));

        if ($detected === null) {
            throw StatementParseException::undetectableCsvColumns($headers);
        }

        return new CsvMapping(
            dateColumn: $detected['date'],
            descriptionColumn: $detected['description'],
            amountColumn: $detected['amount'],
            inflowColumn: $detected['inflow'],
            delimiter: $delimiter,
            hasHeader: $hasHeader,
        );
    }

    /**
     * @param  list<string>  $headers
     * @return array{date: int, description: int, amount: int, inflow: int|null}|null
     */
    private function detectFromHeaders(array $headers): ?array
    {
        $normalized = array_map(
            static fn (string $header): string => Str::ascii(mb_strtolower(trim($header))),
            $headers,
        );

        $date = $this->firstMatching($normalized, ['data', 'date', 'dt_', 'competencia']);
        $description = $this->firstMatching(
            $normalized,
            ['descri', 'histor', 'lancamento', 'memo', 'detalhe', 'estabelec', 'titulo', 'description'],
            skip: [$date],
        );

        // "Saldo" e numerico e mora ao lado do valor; confundi-los importaria
        // o acumulado da conta como se fosse o lancamento.
        $amount = $this->firstMatching($normalized, ['valor', 'amount', 'value', 'montante', 'quantia'], forbidden: ['saldo', 'balance']);
        $inflow = $this->firstMatching($normalized, ['credito', 'entrada', 'credit', 'receita'], forbidden: ['saldo']);
        $outflow = $this->firstMatching($normalized, ['debito', 'saida', 'debit', 'despesa'], forbidden: ['saldo']);

        if ($date === null || $description === null) {
            return null;
        }

        if ($amount !== null) {
            return ['date' => $date, 'description' => $description, 'amount' => $amount, 'inflow' => null];
        }

        if ($inflow !== null && $outflow !== null) {
            return ['date' => $date, 'description' => $description, 'amount' => $outflow, 'inflow' => $inflow];
        }

        return null;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<int|null>  $skip
     * @param  list<string>  $needles
     * @param  list<string>  $forbidden
     */
    private function firstMatching(array $headers, array $needles, array $skip = [], array $forbidden = []): ?int
    {
        foreach ($headers as $index => $header) {
            if (in_array($index, $skip, true)) {
                continue;
            }

            foreach ($forbidden as $term) {
                if (str_contains($header, $term)) {
                    continue 2;
                }
            }

            foreach ($needles as $needle) {
                if (str_contains($header, $needle)) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * Sem cabecalho, o proprio conteudo denuncia cada coluna: a de data e a
     * que o leitor de datas aceita, a de valor e a que o leitor de numeros
     * aceita, e a descricao e a coluna de texto mais longa que sobra.
     *
     * @param  list<list<string>>  $dataRows
     * @return array{date: int, description: int, amount: int, inflow: int|null}|null
     */
    private function detectFromContent(array $dataRows): ?array
    {
        $sample = array_slice($dataRows, 0, 25);

        if ($sample === []) {
            return null;
        }

        $width = max(array_map('count', $sample));
        $dateScore = [];
        $numberScore = [];
        $textLength = [];
        $hasNegative = [];

        for ($column = 0; $column < $width; $column++) {
            $dates = 0;
            $numbers = 0;
            $length = 0;
            $negatives = 0;

            foreach ($sample as $row) {
                $value = $this->cell($row, $column);

                if ($value === '') {
                    continue;
                }

                if (DateReader::parse($value) !== null) {
                    $dates++;
                }

                $number = AmountReader::parse($value);

                if ($number !== null) {
                    $numbers++;
                    $negatives += $number < 0 ? 1 : 0;
                }

                $length += mb_strlen($value);
            }

            $rows = count($sample);
            $dateScore[$column] = $dates / $rows;
            $numberScore[$column] = $numbers / $rows;
            $textLength[$column] = $length / $rows;
            $hasNegative[$column] = $negatives > 0;
        }

        $date = $this->bestColumn($dateScore);

        if ($date === null) {
            return null;
        }

        // Entre as colunas numericas, a que traz negativo e a de movimento; a
        // outra costuma ser o saldo acumulado, que so cresce.
        $numeric = array_keys(array_filter(
            $numberScore,
            static fn (float $score, int $column): bool => $score >= self::DETECTION_THRESHOLD && $column !== $date,
            ARRAY_FILTER_USE_BOTH,
        ));

        $amount = null;

        foreach ($numeric as $column) {
            if ($hasNegative[$column]) {
                $amount = $column;
                break;
            }
        }

        $amount ??= $numeric[0] ?? null;

        if ($amount === null) {
            return null;
        }

        $description = null;
        $longest = 0.0;

        foreach ($textLength as $column => $length) {
            if ($column === $date || $column === $amount || $numberScore[$column] >= self::DETECTION_THRESHOLD) {
                continue;
            }

            if ($length > $longest) {
                $longest = $length;
                $description = $column;
            }
        }

        if ($description === null) {
            return null;
        }

        return ['date' => $date, 'description' => $description, 'amount' => $amount, 'inflow' => null];
    }

    /** @param  array<int, float>  $scores */
    private function bestColumn(array $scores): ?int
    {
        $best = null;
        $bestScore = self::DETECTION_THRESHOLD;

        foreach ($scores as $column => $score) {
            if ($score >= $bestScore) {
                $bestScore = $score;
                $best = $column;
            }
        }

        return $best;
    }

    /**
     * A primeira linha e dado quando ja tem uma data legivel; senao e cabecalho.
     *
     * @param  list<string>  $row
     */
    private function looksLikeData(array $row): bool
    {
        foreach ($row as $value) {
            if (DateReader::parse(trim($value)) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Separador mais frequente nas primeiras linhas. Ponto e virgula vem
     * primeiro no desempate porque e o que o Excel em pt-BR gera.
     */
    private function detectDelimiter(string $body): string
    {
        $head = implode("\n", array_slice(preg_split('/\r\n|\r|\n/', $body) ?: [], 0, 10));
        $best = ';';
        $bestCount = 0;

        foreach ([';', ',', "\t", '|'] as $candidate) {
            $count = substr_count($head, $candidate);

            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Le o arquivo por stream em vez de quebrar por linha: um campo entre
     * aspas pode conter o proprio separador e ate uma quebra de linha.
     *
     * @return list<list<string>>
     */
    private function readRows(string $body, string $delimiter): array
    {
        $handle = fopen('php://memory', 'r+');

        if ($handle === false) {
            return [];
        }

        fwrite($handle, $body);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            // fgetcsv devolve [null] para linha em branco.
            if ($row === [null]) {
                continue;
            }

            $cells = array_map(static fn ($cell): string => (string) $cell, $row);

            if (implode('', array_map(trim(...), $cells)) === '') {
                continue;
            }

            $rows[] = $cells;
        }

        fclose($handle);

        return $rows;
    }

    private function toUtf8(string $contents): string
    {
        $contents = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        return mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }
}
