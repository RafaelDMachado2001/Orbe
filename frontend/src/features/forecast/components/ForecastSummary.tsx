import { TrendingDown, TrendingUp } from 'lucide-react'

import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import { formatCompetence, formatPercent } from '@/lib/format'
import type { ForecastResult } from '@/types/api'

/**
 * Tiles no mesmo estilo dos KPIs do dashboard (KpiCards): saldo atual, saldo
 * projetado no fim do horizonte (com variacao) e confianca do proximo mes —
 * reaproveita literalmente o badge roxo "confianca X%" ja usado la.
 */
export function ForecastSummary({ data }: { data: ForecastResult }) {
  const lastMonth = data.months[data.months.length - 1]
  const nextMonth = data.months[0]

  const projected = lastMonth?.projected_balance ?? data.current_balance
  const delta = data.current_balance !== 0
    ? ((projected - data.current_balance) / Math.abs(data.current_balance)) * 100
    : null
  const isUp = (delta ?? 0) >= 0

  return (
    <section className="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
      <article className="rounded-panel border border-hairline bg-surface p-[18px]">
        <p className="text-[11.5px] font-semibold text-ink-dim">Saldo atual</p>
        <Money
          value={data.current_balance}
          emphasizeCents
          className="my-2 block text-[27px] font-semibold tracking-[-1px]"
        />
        <p className="text-[11px] font-medium text-ink-muted">Consolidado, todas as contas</p>
      </article>

      <article className="rounded-panel border border-purple/20 bg-[linear-gradient(155deg,rgba(160,124,255,0.12),rgba(160,124,255,0.02)_60%),#0F1216] p-[18px]">
        <p className="text-[11.5px] font-semibold text-[#A499BF]">
          {lastMonth ? `Saldo projetado em ${formatCompetence(lastMonth.month)}` : 'Saldo projetado'}
        </p>
        <Money
          value={projected}
          emphasizeCents
          centsClassName="text-[17px] text-[#B9A3F0]"
          className="my-2 block text-[27px] font-semibold tracking-[-1px] text-[#EDE6FF]"
        />
        <div className="flex items-center gap-[7px] text-[11.5px] font-semibold">
          {delta === null ? (
            <span className="text-ink-muted">Sem base de comparação</span>
          ) : (
            <span
              className={cn(
                'flex items-center gap-1 rounded-md px-[7px] py-0.5',
                isUp ? 'bg-green/15 text-green-bright' : 'bg-orange/15 text-orange-light',
              )}
            >
              {isUp ? (
                <TrendingUp className="size-3" aria-hidden="true" />
              ) : (
                <TrendingDown className="size-3" aria-hidden="true" />
              )}
              {formatPercent(Math.abs(delta))}
            </span>
          )}
        </div>
      </article>

      <article className="rounded-panel border border-hairline bg-surface p-[18px]">
        <p className="text-[11.5px] font-semibold text-ink-dim">
          {nextMonth ? `Comprometido em ${formatCompetence(nextMonth.month)}` : 'Comprometido no próximo mês'}
        </p>
        <Money
          value={nextMonth?.committed_amount ?? 0}
          emphasizeCents
          className="my-2 block text-[27px] font-semibold tracking-[-1px]"
        />
        <div className="flex items-center gap-[7px] text-[11.5px] font-semibold text-purple-light">
          <span className="rounded-md bg-purple/[0.16] px-[7px] py-0.5">
            confiança {nextMonth?.confidence ?? 0}%
          </span>
          {nextMonth ? (
            <span className="font-medium text-ink-muted">
              {formatPercent(nextMonth.commitment_rate)} da renda prevista
            </span>
          ) : null}
        </div>
      </article>
    </section>
  )
}

export function ForecastSummarySkeleton() {
  return (
    <section className="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
      {Array.from({ length: 3 }).map((_, index) => (
        <div key={index} className="rounded-panel border border-hairline bg-surface p-[18px]">
          <Skeleton className="h-3 w-32" />
          <Skeleton className="my-3 h-7 w-40" />
          <Skeleton className="h-3 w-24" />
        </div>
      ))}
    </section>
  )
}
