import { ArrowUpRight, CalendarClock, Repeat, Scale } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

import { Card } from '@/components/ui/Card'
import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import type { RecurrencesSummary as Summary } from '@/types/api'

/**
 * Os totais são o **equivalente mensal** de cada regra: uma despesa semanal de
 * R$ 100 entra como R$ 433,33. Somar o valor nominal de frequências diferentes
 * daria um número que não significa nada.
 */
export function RecurrencesSummary({
  summary,
  isRefreshing,
}: {
  summary: Summary
  isRefreshing: boolean
}) {
  return (
    <section aria-label="Resumo das regras fixas" className="grid grid-cols-2 gap-3.5 xl:grid-cols-4">
      <Tile
        icon={Repeat}
        label="Despesas fixas"
        value={summary.expense_total}
        caption="por mês"
        accent="text-[#FF9E5C]"
        iconClass="bg-orange/[0.12] text-orange-light"
        isRefreshing={isRefreshing}
      />
      <Tile
        icon={ArrowUpRight}
        label="Receitas fixas"
        value={summary.income_total}
        caption="por mês"
        accent="text-green-bright"
        iconClass="bg-green/[0.12] text-green-bright"
        isRefreshing={isRefreshing}
      />
      <Tile
        icon={Scale}
        label="Sobra fixa"
        value={summary.net_total}
        caption="receitas menos despesas"
        accent={summary.net_total >= 0 ? 'text-green-bright' : 'text-danger'}
        iconClass="bg-purple/[0.14] text-purple-light"
        isRefreshing={isRefreshing}
      />

      <Card className="flex items-center gap-3.5 p-[15px_18px]">
        <span
          className={cn(
            'grid size-9 shrink-0 place-items-center rounded-[11px]',
            summary.pending_count > 0
              ? 'bg-purple/[0.14] text-purple-light'
              : 'bg-white/[0.05] text-ink-soft',
          )}
        >
          <CalendarClock className="size-4" aria-hidden="true" />
        </span>
        <div className="flex min-w-0 flex-col gap-0.5">
          <span className="text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint">
            A lançar no mês
          </span>
          <span
            className={cn(
              'font-mono text-[19px] font-bold tabular-nums transition-opacity',
              summary.pending_count > 0 ? 'text-purple-light' : 'text-ink',
              isRefreshing && 'opacity-55',
            )}
          >
            {summary.pending_count}
          </span>
          <span className="truncate text-[10.5px] font-medium text-ink-muted">
            {summary.paused_count > 0
              ? `${summary.active_count} ativas · ${summary.paused_count} pausadas`
              : `${summary.active_count} ${summary.active_count === 1 ? 'regra ativa' : 'regras ativas'}`}
          </span>
        </div>
      </Card>
    </section>
  )
}

interface TileProps {
  icon: LucideIcon
  label: string
  value: number
  caption: string
  accent: string
  iconClass: string
  isRefreshing: boolean
}

function Tile({ icon: Icon, label, value, caption, accent, iconClass, isRefreshing }: TileProps) {
  return (
    <Card className="flex items-center gap-3.5 p-[15px_18px]">
      <span className={cn('grid size-9 shrink-0 place-items-center rounded-[11px]', iconClass)}>
        <Icon className="size-4" aria-hidden="true" />
      </span>
      <div className="flex min-w-0 flex-col gap-0.5">
        <span className="text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint">
          {label}
        </span>
        <Money
          value={value}
          className={cn('text-[19px] font-bold transition-opacity', accent, isRefreshing && 'opacity-55')}
        />
        <span className="truncate text-[10.5px] font-medium text-ink-muted">{caption}</span>
      </div>
    </Card>
  )
}

export function RecurrencesSummarySkeleton() {
  return (
    <div className="grid grid-cols-2 gap-3.5 xl:grid-cols-4">
      {Array.from({ length: 4 }).map((_, index) => (
        <Card key={index} className="flex items-center gap-3.5 p-[15px_18px]">
          <Skeleton className="size-9 rounded-[11px]" />
          <div className="flex flex-1 flex-col gap-2">
            <Skeleton className="h-2.5 w-20" />
            <Skeleton className="h-4 w-24" />
          </div>
        </Card>
      ))}
    </div>
  )
}
