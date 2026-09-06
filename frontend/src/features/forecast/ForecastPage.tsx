import { useState } from 'react'

import { AppShell } from '@/components/layout/AppShell'
import { Select } from '@/components/ui/Select'
import { ErrorState } from '@/components/ui/States'
import { apiErrorMessage } from '@/lib/api'

import { FORECAST_HORIZON_OPTIONS, useForecast } from './api'
import { ForecastChart, ForecastChartSkeleton } from './components/ForecastChart'
import { ForecastSummary, ForecastSummarySkeleton } from './components/ForecastSummary'

/**
 * Previsao financeira. Nao navega um mes especifico como as outras telas —
 * navega um horizonte a partir de hoje, combinando recorrencias ativas,
 * parcelas futuras ja assumidas e a media movel do que sobra. Tudo que a
 * tela mostra ja vem calculado do endpoint /forecast; aqui so desenhamos.
 */
export function ForecastPage() {
  const [horizon, setHorizon] = useState(12)

  const { data, isPending, isError, error, refetch, isFetching } = useForecast(horizon)

  return (
    <AppShell>
      <header className="flex flex-wrap items-center gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-[22px] font-bold tracking-[-0.4px]">Previsão financeira</h1>
          <p className="text-[12.5px] font-medium text-ink-dim">
            Projeção combinando despesas fixas, parcelas em aberto e a média dos últimos meses
          </p>
        </div>

        <div className="ml-auto flex items-center gap-2.5">
          <Select
            aria-label="Horizonte da previsão"
            options={FORECAST_HORIZON_OPTIONS}
            value={String(horizon)}
            onChange={(event) => setHorizon(Number(event.target.value))}
            className="w-[140px]"
          />
        </div>
      </header>

      {isError ? (
        <ErrorState
          description={apiErrorMessage(error, 'Não foi possível carregar a previsão.')}
          onRetry={() => void refetch()}
        />
      ) : null}

      {isPending ? (
        <div className="flex flex-col gap-4">
          <ForecastSummarySkeleton />
          <ForecastChartSkeleton />
        </div>
      ) : null}

      {data ? (
        <div className="flex flex-col gap-4" aria-busy={isFetching}>
          <ForecastSummary data={data} />
          <ForecastChart data={data} />
        </div>
      ) : null}
    </AppShell>
  )
}
