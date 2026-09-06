import { FileDown } from 'lucide-react'
import { useState } from 'react'

import { useToast } from '@/app/useToast'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/Button'
import { Select } from '@/components/ui/Select'
import { ErrorState } from '@/components/ui/States'
import { Toast } from '@/components/ui/Toast'
import { apiErrorMessage } from '@/lib/api'

import { useAnnualReport, useExportAnnualReport } from './api'
import { AnnualChart, AnnualChartSkeleton } from './components/AnnualChart'
import { AnnualSummary, AnnualSummarySkeleton } from './components/AnnualSummary'
import { CategoryRanking, CategoryRankingSkeleton } from './components/CategoryRanking'

const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 6 }, (_, index) => {
  const year = currentYear - index

  return { value: String(year), label: String(year) }
})

/**
 * Relatório anual. O dashboard já mostra o mês atual em detalhe — aqui o que
 * importa é o que o dashboard não mostra: o ano inteiro comparado, o ranking
 * de categorias do ano e um documento exportável para guardar ou compartilhar.
 */
export function ReportsPage() {
  const [year, setYear] = useState(currentYear)
  const { data, isPending, isError, error, refetch, isFetching } = useAnnualReport(year)
  const exportReport = useExportAnnualReport()
  const { toast, notify, dismiss } = useToast()

  async function handleExport(format: 'csv' | 'pdf') {
    try {
      await exportReport.mutateAsync({ year, format })
    } catch (exportError) {
      notify(apiErrorMessage(exportError, 'Não foi possível exportar o relatório.'))
    }
  }

  return (
    <AppShell>
      <header className="flex flex-wrap items-center gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-[22px] font-bold tracking-[-0.4px]">Relatórios</h1>
          <p className="text-[12.5px] font-medium text-ink-dim">
            Resultado do ano, comparado e por categoria
          </p>
        </div>

        <div className="ml-auto flex items-center gap-2.5">
          <Select
            aria-label="Ano do relatório"
            options={yearOptions}
            value={String(year)}
            onChange={(event) => setYear(Number(event.target.value))}
            className="w-[110px]"
          />
          <Button
            variant="ghost"
            onClick={() => void handleExport('csv')}
            isLoading={exportReport.isPending && exportReport.variables?.format === 'csv'}
          >
            <FileDown className="size-3.5" aria-hidden="true" />
            CSV
          </Button>
          <Button
            onClick={() => void handleExport('pdf')}
            isLoading={exportReport.isPending && exportReport.variables?.format === 'pdf'}
          >
            <FileDown className="size-3.5" aria-hidden="true" />
            PDF
          </Button>
        </div>
      </header>

      {isError ? (
        <ErrorState
          description={apiErrorMessage(error, 'Não foi possível carregar o relatório.')}
          onRetry={() => void refetch()}
        />
      ) : null}

      {isPending ? (
        <div className="flex flex-col gap-4">
          <AnnualSummarySkeleton />
          <AnnualChartSkeleton />
          <CategoryRankingSkeleton />
        </div>
      ) : null}

      {data ? (
        <div className="flex flex-col gap-4" aria-busy={isFetching}>
          <AnnualSummary report={data} />
          <AnnualChart report={data} />
          <CategoryRanking categories={data.category_ranking} />
        </div>
      ) : null}

      <Toast toast={toast} onDismiss={dismiss} />
    </AppShell>
  )
}
