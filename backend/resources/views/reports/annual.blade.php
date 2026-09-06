<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    @php
        $meses = ['01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril', '05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'];
        $mesLabel = fn (string $ym) => $meses[substr($ym, 5, 2)] . '/' . substr($ym, 0, 4);
        $brl = fn (float $v) => 'R$ ' . number_format($v, 2, ',', '.');
    @endphp
    body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1A1D21; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    .subtitle { color: #6E7681; margin-top: 0; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
    th, td { padding: 5px 8px; text-align: right; border-bottom: 1px solid #E4E7EB; }
    th:first-child, td:first-child { text-align: left; }
    th { background: #F2F3F5; font-size: 10px; text-transform: uppercase; color: #4A525E; }
    .totals td { font-weight: bold; border-top: 2px solid #1A1D21; border-bottom: none; }
    .section-title { font-size: 13px; font-weight: bold; margin: 0 0 8px; }
    .kpis { width: 100%; margin-bottom: 22px; }
    .kpis td { border: none; padding: 0 18px 0 0; text-align: left; }
    .kpi-label { font-size: 10px; text-transform: uppercase; color: #6E7681; }
    .kpi-value { font-size: 16px; font-weight: bold; }
    .positive { color: #1D9260; }
    .negative { color: #C0392B; }
</style>
</head>
<body>
    <h1>Relatório anual — {{ $report->year }}</h1>
    <p class="subtitle">Orbe · Gerado em {{ now()->format('d/m/Y') }}</p>

    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-label">Receitas do ano</div>
                <div class="kpi-value">{{ $brl($report->totalIncome) }}</div>
            </td>
            <td>
                <div class="kpi-label">Despesas do ano</div>
                <div class="kpi-value">{{ $brl($report->totalExpense) }}</div>
            </td>
            <td>
                <div class="kpi-label">Resultado do ano</div>
                <div class="kpi-value {{ $report->balance() >= 0 ? 'positive' : 'negative' }}">{{ $brl($report->balance()) }}</div>
            </td>
            <td>
                <div class="kpi-label">Vs. ano anterior</div>
                <div class="kpi-value">
                    @if ($report->yearOverYear() === null)
                        —
                    @else
                        {{ $report->yearOverYear() >= 0 ? '+' : '' }}{{ number_format($report->yearOverYear(), 1, ',', '.') }}%
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <p class="section-title">Resultado mês a mês</p>
    <table>
        <thead>
            <tr>
                <th>Mês</th>
                <th>Receitas</th>
                <th>Despesas</th>
                <th>Saldo do mês</th>
                <th>Saldo consolidado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report->months as $index => $month)
                <tr>
                    <td>{{ $mesLabel($month->month) }}</td>
                    <td>{{ $brl($month->income) }}</td>
                    <td>{{ $brl($month->expense) }}</td>
                    <td>{{ $brl($month->balance()) }}</td>
                    <td>{{ $brl($report->balanceSeries[$index] ?? 0.0) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals">
                <td>Total</td>
                <td>{{ $brl($report->totalIncome) }}</td>
                <td>{{ $brl($report->totalExpense) }}</td>
                <td>{{ $brl($report->balance()) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <p class="section-title">Categorias que mais pesaram no ano</p>
    <table>
        <thead>
            <tr>
                <th>Categoria</th>
                <th>Total gasto</th>
                <th>% do total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report->categoryRanking as $slice)
                <tr>
                    <td>{{ $slice['name'] }}</td>
                    <td>{{ $brl($slice['total']) }}</td>
                    <td>{{ number_format($slice['percentage'], 1, ',', '.') }}%</td>
                </tr>
            @empty
                <tr><td colspan="3">Nenhuma despesa categorizada neste ano.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
