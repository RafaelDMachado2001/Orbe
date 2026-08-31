import { format } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import { Plus } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'

import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { ErrorState } from '@/components/ui/States'
import { apiErrorMessage } from '@/lib/api'
import { capitalize, formatCompetence } from '@/lib/format'

import { useDashboard } from './api'
import { AccountsPanel } from './components/AccountsPanel'
import { AlertsStrip } from './components/AlertsStrip'
import { CardsPanel } from './components/CardsPanel'
import { CategoryBreakdown } from './components/CategoryBreakdown'
import { ForecastStrip } from './components/ForecastStrip'
import { IncomeExpenseChart } from './components/IncomeExpenseChart'
import { KpiCards, KpiCardsSkeleton } from './components/KpiCards'
import { LatestMovements } from './components/LatestMovements'
import { MonthSwitcher } from './components/MonthSwitcher'

/**
 * Visão geral do mês. Toda a informação — KPIs, série do gráfico, projeção,
 * alertas — chega pronta do endpoint /dashboard: aqui só desenhamos.
 */
export function DashboardPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const monthParam = searchParams.get('mes') ?? undefined

  const { data, isPending, isError, error, refetch, isFetching } = useDashboard(monthParam)

  const month = data?.month ?? monthParam ?? format(new Date(), 'yyyy-MM')

  function handleMonthChange(next: string) {
    setSearchParams(next === format(new Date(), 'yyyy-MM') ? {} : { mes: next })
  }

  return (
    <AppShell
      cardsCount={data?.cards.length}
      commitmentRate={data?.forecast.commitment_rate ?? null}
      nextMonthLabel={
        data?.kpis.projection.month ? formatCompetence(data.kpis.projection.month) : undefined
      }
    >
      <header className="flex flex-wrap items-center gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-[22px] font-bold tracking-[-0.4px]">
            Balanço de {formatCompetence(month)}
          </h1>
          <p className="text-[12.5px] font-medium text-ink-dim">
            {data
              ? `Atualizado às ${format(new Date(data.generated_at), 'HH:mm', { locale: ptBR })} · ${data.accounts.length} ${data.accounts.length === 1 ? 'conta' : 'contas'} · ${data.cards.length} ${data.cards.length === 1 ? 'cartão' : 'cartões'}`
              : 'Carregando o balanço do mês…'}
          </p>
        </div>

        <div className="ml-auto flex items-center gap-2.5">
          <MonthSwitcher month={month} onChange={handleMonthChange} />
          <Button variant="ghost" disabled title="Disponível na próxima entrega">
            Exportar
          </Button>
          <Button disabled title="Disponível na próxima entrega">
            <Plus className="size-3.5" aria-hidden="true" />
            Nova despesa
          </Button>
        </div>
      </header>

      {isError ? (
        <ErrorState
          description={apiErrorMessage(error, 'Não foi possível carregar o balanço do mês.')}
          onRetry={() => void refetch()}
        />
      ) : null}

      {isPending ? <DashboardSkeleton /> : null}

      {data ? (
        <div
          className="flex flex-col gap-5"
          aria-busy={isFetching}
          data-month={capitalize(formatCompetence(month))}
        >
          <AlertsStrip alerts={data.alerts} />

          <KpiCards data={data} />

          <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.55fr_1fr]">
            <IncomeExpenseChart data={data.chart} />

            <div className="flex flex-col gap-4">
              <CardsPanel cards={data.cards} />
              <AccountsPanel accounts={data.accounts} />
            </div>
          </section>

          <section className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1fr_1.55fr]">
            <CategoryBreakdown categories={data.categories} month={data.month} />
            <LatestMovements movements={data.latest_movements} />
          </section>

          <ForecastStrip forecast={data.forecast} />
        </div>
      ) : null}
    </AppShell>
  )
}

function DashboardSkeleton() {
  return (
    <div className="flex flex-col gap-5">
      <KpiCardsSkeleton />

      <section className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[1.55fr_1fr]">
        <Card className="h-[318px]">
          <Skeleton className="h-4 w-40" />
          <Skeleton className="mt-6 h-[228px] w-full" />
        </Card>
        <div className="flex flex-col gap-4">
          <Card className="h-[260px]">
            <Skeleton className="h-4 w-28" />
            <Skeleton className="mt-4 h-[120px] w-full" />
            <Skeleton className="mt-3 h-[54px] w-full" />
          </Card>
          <Card className="h-[190px]">
            <Skeleton className="h-4 w-32" />
            <Skeleton className="mt-4 h-[110px] w-full" />
          </Card>
        </div>
      </section>
    </div>
  )
}
