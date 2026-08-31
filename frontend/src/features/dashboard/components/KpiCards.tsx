import { TrendingDown, TrendingUp } from 'lucide-react'

import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import { formatPercent } from '@/lib/format'
import type { Dashboard } from '@/types/api'

export function KpiCards({ data }: { data: Dashboard }) {
  const { consolidated_balance: balance, income, expense, projection } = data.kpis
  const delta = balance.delta_percent
  const isUp = (delta ?? 0) >= 0

  return (
    <section className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
      <article className="rounded-panel border border-green/20 bg-[linear-gradient(155deg,rgba(53,214,138,0.13),rgba(53,214,138,0.02)_60%),#0F1216] p-[18px]">
        <p className="text-[11.5px] font-semibold tracking-[0.02em] text-[#8FA79A]">
          Saldo consolidado
        </p>
        <Money
          value={balance.value}
          emphasizeCents
          centsClassName="text-[17px] text-[#7FD9AB]"
          className="my-2 block text-[27px] font-semibold tracking-[-1px] text-[#F2FFF8]"
        />
        <div className="flex items-center gap-[7px] text-[11.5px] font-semibold">
          {delta === null ? (
            <span className="text-ink-muted">Sem base de comparação</span>
          ) : (
            <>
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
              <span className="font-medium text-ink-muted">{balance.comparison_label}</span>
            </>
          )}
        </div>
      </article>

      <article className="rounded-panel border border-hairline bg-surface p-[18px]">
        <p className="text-[11.5px] font-semibold text-ink-dim">Receitas do mês</p>
        <Money
          value={income.value}
          emphasizeCents
          className="my-2 block text-[27px] font-semibold tracking-[-1px]"
        />
        <div className="h-[5px] overflow-hidden rounded bg-white/[0.07]">
          <div className="h-full w-full bg-green" />
        </div>
        <p className="mt-[7px] text-[11px] font-medium text-ink-muted">
          {income.sources_count > 0
            ? `${income.sources_count} ${income.sources_count === 1 ? 'fonte' : 'fontes'} · ${income.sources
                .slice(0, 3)
                .join(', ')}`
            : 'Nenhuma receita lançada'}
        </p>
      </article>

      <article className="rounded-panel border border-hairline bg-surface p-[18px]">
        <p className="text-[11.5px] font-semibold text-ink-dim">Despesas do mês</p>
        <Money
          value={expense.value}
          emphasizeCents
          className="my-2 block text-[27px] font-semibold tracking-[-1px]"
        />
        <div className="h-[5px] overflow-hidden rounded bg-white/[0.07]">
          <div
            className="h-full rounded bg-[linear-gradient(90deg,#FF8A3D,#FFB072)]"
            style={{ width: `${Math.min(expense.budget_usage_percent ?? 0, 100)}%` }}
          />
        </div>
        <p
          className={cn(
            'mt-[7px] text-[11px] font-semibold',
            (expense.budget_usage_percent ?? 0) >= 100 ? 'text-orange' : 'text-orange-light',
          )}
        >
          {expense.budget_usage_percent === null
            ? 'Sem orçamento definido'
            : `${formatPercent(expense.budget_usage_percent, 0)} do orçamento definido`}
        </p>
      </article>

      <article className="rounded-panel border border-purple/20 bg-[linear-gradient(155deg,rgba(160,124,255,0.12),rgba(160,124,255,0.02)_60%),#0F1216] p-[18px]">
        <p className="text-[11.5px] font-semibold text-[#A499BF]">
          {projection.label ?? 'Projeção'}
        </p>
        <Money
          value={projection.value}
          emphasizeCents
          centsClassName="text-[17px] text-[#B9A3F0]"
          className="my-2 block text-[27px] font-semibold tracking-[-1px] text-[#EDE6FF]"
        />
        <div className="flex items-center gap-[7px] text-[11.5px] font-semibold text-purple-light">
          <span className="rounded-md bg-purple/[0.16] px-[7px] py-0.5">
            confiança {projection.confidence ?? 0}%
          </span>
        </div>
      </article>
    </section>
  )
}

export function KpiCardsSkeleton() {
  return (
    <section className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
      {Array.from({ length: 4 }).map((_, index) => (
        <div key={index} className="rounded-panel border border-hairline bg-surface p-[18px]">
          <Skeleton className="h-3 w-28" />
          <Skeleton className="my-3 h-7 w-40" />
          <Skeleton className="h-[5px] w-full" />
        </div>
      ))}
    </section>
  )
}
