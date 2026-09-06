import { ArrowDownRight, ArrowUpRight, Landmark, Wallet } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

import { Card } from '@/components/ui/Card'
import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import { formatPercent } from '@/lib/format'
import type { AccountsSummary as Summary } from '@/types/api'

/**
 * Painel do topo. O consolidado soma apenas contas ativas — conta arquivada
 * guarda histórico, não dinheiro disponível. "Entrou" e "saiu" contam tudo o
 * que passou pelas contas no mês, transferência inclusa.
 */
export function AccountsSummary({
  summary,
  isRefreshing,
}: {
  summary: Summary
  isRefreshing: boolean
}) {
  const delta = summary.delta_percent

  return (
    <section aria-label="Resumo das contas" className="grid grid-cols-2 gap-3.5 xl:grid-cols-4">
      <Card className="flex items-center gap-3.5 p-[15px_18px]">
        <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-green/[0.12] text-green-bright">
          <Wallet className="size-4" aria-hidden="true" />
        </span>
        <div className="flex min-w-0 flex-col gap-0.5">
          <span className="text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint">
            Saldo consolidado
          </span>
          <Money
            value={summary.consolidated_balance}
            className={cn(
              'text-[19px] font-bold transition-opacity',
              summary.consolidated_balance >= 0 ? 'text-ink' : 'text-danger',
              isRefreshing && 'opacity-55',
            )}
          />
          <span className="truncate text-[10.5px] font-medium text-ink-muted">
            {delta === null
              ? 'primeiro mês com saldo'
              : `${delta >= 0 ? '+' : '−'}${formatPercent(Math.abs(delta), 1)} vs. mês anterior`}
          </span>
        </div>
      </Card>

      <Tile
        icon={ArrowUpRight}
        label="Entrou no mês"
        value={summary.month_in}
        caption="nas contas ativas"
        accent="text-green-bright"
        iconClass="bg-green/[0.12] text-green-bright"
        isRefreshing={isRefreshing}
      />
      <Tile
        icon={ArrowDownRight}
        label="Saiu no mês"
        value={summary.month_out}
        caption="transferências inclusas"
        accent="text-[#FF9E5C]"
        iconClass="bg-orange/[0.12] text-orange-light"
        isRefreshing={isRefreshing}
      />

      <Card className="flex items-center gap-3.5 p-[15px_18px]">
        <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-purple/[0.14] text-purple-light">
          <Landmark className="size-4" aria-hidden="true" />
        </span>
        <div className="flex min-w-0 flex-col gap-0.5">
          <span className="text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint">
            Contas ativas
          </span>
          <span
            className={cn(
              'font-mono text-[19px] font-bold tabular-nums text-ink transition-opacity',
              isRefreshing && 'opacity-55',
            )}
          >
            {summary.active_count}
          </span>
          <span className="truncate text-[10.5px] font-medium text-ink-muted">
            {summary.banks_count} {summary.banks_count === 1 ? 'instituição' : 'instituições'}
            {summary.archived_count > 0 ? ` · ${summary.archived_count} arquivada${summary.archived_count === 1 ? '' : 's'}` : ''}
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

export function AccountsSummarySkeleton() {
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
