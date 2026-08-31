import { FolderTree, Layers, TrendingDown, TrendingUp } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

import { Card } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import type { CategoriesSummary as Summary } from '@/types/api'

/**
 * Contagens, não valores: o dinheiro do mês está na árvore, categoria a
 * categoria. O que interessa aqui é o tamanho do plano de contas e quanto dele
 * está de fato em uso — categoria que ninguém usa há meses é ruído no seletor
 * de lançamentos.
 */
export function CategoriesSummary({
  summary,
  isRefreshing,
}: {
  summary: Summary
  isRefreshing: boolean
}) {
  return (
    <section aria-label="Resumo das categorias" className="grid grid-cols-2 gap-3.5 xl:grid-cols-4">
      <Tile
        icon={FolderTree}
        label="Categorias"
        value={summary.total}
        caption="no plano de contas"
        accent="text-ink"
        iconClass="bg-purple/[0.14] text-purple-light"
        isRefreshing={isRefreshing}
      />
      <Tile
        icon={TrendingDown}
        label="De despesa"
        value={summary.expense_count}
        caption="classificam o que sai"
        accent="text-[#FF9E5C]"
        iconClass="bg-orange/[0.12] text-orange-light"
        isRefreshing={isRefreshing}
      />
      <Tile
        icon={TrendingUp}
        label="De receita"
        value={summary.income_count}
        caption="classificam o que entra"
        accent="text-green-bright"
        iconClass="bg-green/[0.12] text-green-bright"
        isRefreshing={isRefreshing}
      />
      <Tile
        icon={Layers}
        label="Usadas no mês"
        value={summary.in_use_count}
        caption={
          summary.unused_count > 0
            ? `${summary.unused_count} sem movimento`
            : 'todas com movimento'
        }
        accent="text-ink"
        iconClass="bg-white/[0.05] text-ink-soft"
        isRefreshing={isRefreshing}
      />
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
        <span
          className={cn(
            'font-mono text-[19px] font-bold tabular-nums transition-opacity',
            accent,
            isRefreshing && 'opacity-55',
          )}
        >
          {value}
        </span>
        <span className="truncate text-[10.5px] font-medium text-ink-muted">{caption}</span>
      </div>
    </Card>
  )
}

export function CategoriesSummarySkeleton() {
  return (
    <div className="grid grid-cols-2 gap-3.5 xl:grid-cols-4">
      {Array.from({ length: 4 }).map((_, index) => (
        <Card key={index} className="flex items-center gap-3.5 p-[15px_18px]">
          <Skeleton className="size-9 rounded-[11px]" />
          <div className="flex flex-1 flex-col gap-2">
            <Skeleton className="h-2.5 w-20" />
            <Skeleton className="h-4 w-16" />
          </div>
        </Card>
      ))}
    </div>
  )
}
