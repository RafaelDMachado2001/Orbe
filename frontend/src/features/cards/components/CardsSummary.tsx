import { CalendarClock, CreditCard, TrendingUp, Wallet } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

import { Card } from '@/components/ui/Card'
import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import { formatDate, formatPercent } from '@/lib/format'
import type { CardsSummary as Summary } from '@/types/api'

/**
 * Painel do topo. O limite somado ignora cartão arquivado: ele não está mais
 * disponível para gastar, ainda que a dívida que ficou continue no "em aberto".
 */
export function CardsSummary({ summary, isRefreshing }: { summary: Summary; isRefreshing: boolean }) {
  const nextDue = summary.next_due

  return (
    <section aria-label="Resumo dos cartões" className="grid grid-cols-2 gap-3.5 xl:grid-cols-4">
      <Tile
        icon={Wallet}
        label="Limite total"
        value={summary.limit_total}
        caption={`${summary.active_count} ${summary.active_count === 1 ? 'cartão ativo' : 'cartões ativos'}`}
        iconClass="bg-white/[0.05] text-ink-soft"
        isRefreshing={isRefreshing}
      />
      <Tile
        icon={TrendingUp}
        label="Limite usado"
        value={summary.used_total}
        caption={`${formatPercent(summary.usage_percent, 0)} do total`}
        accent="text-[#FF9E5C]"
        iconClass="bg-orange/[0.12] text-orange-light"
        isRefreshing={isRefreshing}
      />
      <Tile
        icon={CreditCard}
        label="Disponível"
        value={summary.available_total}
        caption="para novas compras"
        accent="text-green-bright"
        iconClass="bg-green/[0.12] text-green-bright"
        isRefreshing={isRefreshing}
      />

      <Card className="flex items-center gap-3.5 p-[15px_18px]">
        <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-purple/[0.14] text-purple-light">
          <CalendarClock className="size-4" aria-hidden="true" />
        </span>
        <div className="flex min-w-0 flex-col gap-0.5">
          <span className="text-[10.5px] font-bold uppercase tracking-[0.08em] text-ink-faint">
            Faturas em aberto
          </span>
          <Money
            value={summary.outstanding_total}
            className={cn(
              'text-[19px] font-bold text-purple-light transition-opacity',
              isRefreshing && 'opacity-55',
            )}
          />
          <span className="truncate text-[10.5px] font-medium text-ink-muted">
            {nextDue
              ? `${nextDue.card} vence ${formatDate(nextDue.due_date)}`
              : 'nada a pagar por enquanto'}
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
  accent?: string
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
          className={cn(
            'text-[19px] font-bold transition-opacity',
            accent ?? 'text-ink',
            isRefreshing && 'opacity-55',
          )}
        />
        <span className="truncate text-[10.5px] font-medium text-ink-muted">{caption}</span>
      </div>
    </Card>
  )
}

export function CardsSummarySkeleton() {
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
