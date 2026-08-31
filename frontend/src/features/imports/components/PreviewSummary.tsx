import { ArrowDownRight, ArrowUpRight, CopyCheck, ListChecks } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

import { Money } from '@/components/ui/Money'
import { cn } from '@/lib/cn'
import type { ImportPreviewSummary } from '@/types/api'

interface Props {
  summary: ImportPreviewSummary
  /** Quantas linhas estão marcadas agora — muda a cada clique na tabela. */
  selectedCount: number
  isRefreshing: boolean
}

/**
 * O que o arquivo traz, em quatro números.
 *
 * Receita e despesa somam o que está marcado, não o arquivo inteiro: quem lê
 * "vai entrar R$ 7.200" precisa que esse seja o valor que vai entrar mesmo
 * depois de desmarcar metade das linhas.
 */
export function PreviewSummary({ summary, selectedCount, isRefreshing }: Props) {
  return (
    <div
      className={cn(
        'grid grid-cols-2 gap-2.5 transition-opacity lg:grid-cols-4',
        isRefreshing && 'opacity-55',
      )}
    >
      <Tile
        icon={ListChecks}
        label="Selecionados"
        value={`${selectedCount} de ${summary.total}`}
        iconClass="bg-green/[0.12] text-green-bright"
      />
      <Tile
        icon={CopyCheck}
        label="Já existem"
        value={String(summary.duplicates)}
        // Repetido e recusado são coisas diferentes: o repetido você ainda
        // pode marcar, o recusado o destino não aceita de jeito nenhum.
        caption={
          summary.blocked > 0
            ? `${summary.blocked} ${summary.blocked === 1 ? 'não importável' : 'não importáveis'}`
            : undefined
        }
        iconClass="bg-orange/[0.12] text-orange-light"
      />
      <Tile
        icon={ArrowUpRight}
        label="Entradas"
        money={summary.income}
        accent="text-green-bright"
        iconClass="bg-green/[0.12] text-green-bright"
      />
      <Tile
        icon={ArrowDownRight}
        label="Saídas"
        money={summary.expense}
        accent="text-[#FF9E5C]"
        iconClass="bg-orange/[0.12] text-orange-light"
      />
    </div>
  )
}

interface TileProps {
  icon: LucideIcon
  label: string
  value?: string
  money?: number
  caption?: string
  accent?: string
  iconClass: string
}

function Tile({ icon: Icon, label, value, money, caption, accent, iconClass }: TileProps) {
  return (
    <div className="flex items-center gap-3 rounded-[13px] border border-hairline bg-surface-alt px-3.5 py-3">
      <span className={cn('grid size-8 shrink-0 place-items-center rounded-[10px]', iconClass)}>
        <Icon className="size-3.5" aria-hidden="true" />
      </span>
      <div className="flex min-w-0 flex-col gap-0.5">
        <span className="text-[10px] font-bold uppercase tracking-[0.08em] text-ink-faint">
          {label}
        </span>
        {money === undefined ? (
          <span className={cn('text-[15px] font-bold', accent ?? 'text-ink')}>{value}</span>
        ) : (
          <Money value={money} className={cn('text-[15px] font-bold', accent ?? 'text-ink')} />
        )}
        {caption ? (
          <span className="truncate text-[10px] font-medium text-ink-muted">{caption}</span>
        ) : null}
      </div>
    </div>
  )
}
