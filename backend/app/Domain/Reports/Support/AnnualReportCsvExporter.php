<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Reports\DTOs\AnnualReportResult;

/** Monta o CSV do relatorio anual: um bloco de meses, um bloco de categorias. */
final class AnnualReportCsvExporter
{
    public function handle(AnnualReportResult $report): string
    {
        $stream = fopen('php://temp', 'r+');

        fputcsv($stream, ["Relatório anual {$report->year}"]);
        fputcsv($stream, []);

        fputcsv($stream, ['Mês', 'Receitas', 'Despesas', 'Saldo do mês', 'Saldo consolidado']);

        foreach ($report->months as $index => $month) {
            fputcsv($stream, [
                $month->month,
                number_format($month->income, 2, ',', ''),
                number_format($month->expense, 2, ',', ''),
                number_format($month->balance(), 2, ',', ''),
                number_format($report->balanceSeries[$index] ?? 0.0, 2, ',', ''),
            ]);
        }

        fputcsv($stream, []);
        fputcsv($stream, ['Total', number_format($report->totalIncome, 2, ',', ''), number_format($report->totalExpense, 2, ',', ''), number_format($report->balance(), 2, ',', '')]);

        fputcsv($stream, []);
        fputcsv($stream, ['Categoria', 'Total gasto no ano', '% do total']);

        foreach ($report->categoryRanking as $slice) {
            fputcsv($stream, [$slice['name'], number_format($slice['total'], 2, ',', ''), number_format($slice['percentage'], 1, ',', '')]);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        // BOM UTF-8 na frente: sem ele o Excel le "Relat\xC3\xB3rio" errado.
        return "\xEF\xBB\xBF".($content === false ? '' : $content);
    }
}
