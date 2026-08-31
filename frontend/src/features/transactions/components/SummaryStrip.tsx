import { ArrowDownLeft, ArrowUpRight, Receipt, Scale } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

import { Card } from '@/components/ui/Card'
import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import { formatDate } from '@/lib/format'
import type { TransactionSummary } from '@/types/api'

interface Props {
  summary: TransactionSummary
  from: string
  to: string
  /** Some quando a lista está sendo refeita, para o número não parecer travado. */
  isRefreshing: boolean
}

/**
 * Os totais vêm somados da API sobre o período inteiro, não sobre a página
 * visível — quem olha "saídas do período" quer o período.
 */
export function SummaryStrip({ summary, from, to, isRefreshing }: Props) {
  return (
    <section
      aria-label="Totais do período"
      aria-busy={isRefreshing}
      className="grid grid-cols-2 gap-3.5 xl:grid-cols-4"
    >
      <Tile
        icon={ArrowUpRight}
        label="Entradas"
        value={summary.income}
        accent="text-green-bright"
        iconClass="bg-green/[0.12] text-green-bright"
        isRefreshing={isRefreshing}
      />
      <Tile
        icon={ArrowDownLeft}
        label="Saídas"
        value={summary.expense}
        accent="text-[#FF9E5C]"
        iconClass="bg-orange/[0.12] text-orange-light"
        isRefreshing={isRefreshing}
      />
      <Tile
        icon={Scale}
        label="Resultado"
        value={summary.net}
        accent={summary.net >= 0 ? 'text-green-bright' : 'text-danger'}
        iconClass="bg-purple/[0.14] text-purple-light"
        isRefreshing={isRefreshing}
      />

      <Card className="flex items-center gap-3.5 p-[15px_18px]">
        <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-white/[0.05] text-ink-soft">
          <Receipt className="size-4" aria-hidden="true" />
        </span>
        <div className="flex min-w-0 flex-col gap-0.5">
          <span className="text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint">
            Lançamentos
          </span>
          <span
            className={cn(
              'font-mono text-[19px] font-bold tabular-nums text-ink transition-opacity',
              isRefreshing && 'opacity-55',
            )}
          >
            {summary.entries}
          </span>
          <span className="truncate text-[10.5px] font-medium text-ink-muted">
            {formatDate(from)} — {formatDate(to)}
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
  accent: string
  iconClass: string
  isRefreshing: boolean
}

function Tile({ icon: Icon, label, value, accent, iconClass, isRefreshing }: TileProps) {
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
      </div>
    </Card>
  )
}

export function SummaryStripSkeleton() {
  return (
    <div className="grid grid-cols-2 gap-3.5 xl:grid-cols-4">
      {Array.from({ length: 4 }).map((_, index) => (
        <Card key={index} className="flex items-center gap-3.5 p-[15px_18px]">
          <Skeleton className="size-9 rounded-[11px]" />
          <div className="flex flex-1 flex-col gap-2">
            <Skeleton className="h-2.5 w-16" />
            <Skeleton className="h-4 w-24" />
          </div>
        </Card>
      ))}
    </div>
  )
}
