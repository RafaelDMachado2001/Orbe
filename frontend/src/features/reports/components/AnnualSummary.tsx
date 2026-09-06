import { TrendingDown, TrendingUp } from 'lucide-react'

import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import { formatPercent } from '@/lib/format'
import type { AnnualReport } from '@/types/api'

export function AnnualSummary({ report }: { report: AnnualReport }) {
  const isUp = (report.year_over_year ?? 0) >= 0

  return (
    <section className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
      <article className="rounded-panel border border-hairline bg-surface p-[18px]">
        <p className="text-[11.5px] font-semibold text-ink-dim">Receitas do ano</p>
        <Money value={report.total_income} emphasizeCents className="my-2 block text-[27px] font-semibold tracking-[-1px]" />
      </article>

      <article className="rounded-panel border border-hairline bg-surface p-[18px]">
        <p className="text-[11.5px] font-semibold text-ink-dim">Despesas do ano</p>
        <Money value={report.total_expense} emphasizeCents className="my-2 block text-[27px] font-semibold tracking-[-1px]" />
      </article>

      <article className="rounded-panel border border-green/20 bg-[linear-gradient(155deg,rgba(53,214,138,0.13),rgba(53,214,138,0.02)_60%),#0F1216] p-[18px]">
        <p className="text-[11.5px] font-semibold tracking-[0.02em] text-[#8FA79A]">Resultado do ano</p>
        <Money
          value={report.balance}
          emphasizeCents
          centsClassName="text-[17px] text-[#7FD9AB]"
          className="my-2 block text-[27px] font-semibold tracking-[-1px] text-[#F2FFF8]"
        />
      </article>

      <article className="rounded-panel border border-hairline bg-surface p-[18px]">
        <p className="text-[11.5px] font-semibold text-ink-dim">Vs. ano anterior</p>
        {report.year_over_year === null ? (
          <p className="my-2 text-[27px] font-semibold tracking-[-1px] text-ink-muted">—</p>
        ) : (
          <div className="my-2 flex items-center gap-[7px]">
            <span
              className={cn(
                'flex items-center gap-1 rounded-md px-[7px] py-1 text-[15px] font-bold',
                isUp ? 'bg-green/15 text-green-bright' : 'bg-orange/15 text-orange-light',
              )}
            >
              {isUp ? <TrendingUp className="size-3.5" aria-hidden="true" /> : <TrendingDown className="size-3.5" aria-hidden="true" />}
              {formatPercent(Math.abs(report.year_over_year))}
            </span>
          </div>
        )}
        <p className="text-[11px] font-medium text-ink-muted">
          {report.previous_year_balance === null ? 'Sem ano anterior para comparar' : 'Resultado, ano contra ano'}
        </p>
      </article>
    </section>
  )
}

export function AnnualSummarySkeleton() {
  return (
    <section className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
      {Array.from({ length: 4 }).map((_, index) => (
        <div key={index} className="rounded-panel border border-hairline bg-surface p-[18px]">
          <Skeleton className="h-3 w-28" />
          <Skeleton className="my-3 h-7 w-40" />
        </div>
      ))}
    </section>
  )
}
