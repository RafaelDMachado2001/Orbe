import { Archive, ArchiveRestore, ChevronDown, Pencil, Plus, Trash2, Undo2 } from 'lucide-react'
import { useState } from 'react'

import { Button } from '@/components/ui/Button'
import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { cn } from '@/lib/cn'
import { formatDate, formatPercent } from '@/lib/format'
import type { GoalContribution, GoalRecord } from '@/types/api'

import { useGoalContributions } from '../api'

interface Props {
  goal: GoalRecord
  onNewContribution: () => void
  onEdit: () => void
  onArchive: () => void
  onDelete: () => void
  onDeleteContribution: (contribution: GoalContribution) => void
}

export function GoalCard({ goal, onNewContribution, onEdit, onArchive, onDelete, onDeleteContribution }: Props) {
  const [isExpanded, setIsExpanded] = useState(false)
  const { data: contributions, isPending } = useGoalContributions(goal.id, isExpanded)

  const width = Math.min(goal.progress, 100)

  return (
    <div className={cn('rounded-[16px] border border-hairline bg-surface-alt p-[16px_18px]', goal.is_archived && 'opacity-60')}>
      <div className="flex flex-wrap items-start gap-3">
        <div className="min-w-0 flex-1">
          <p className="truncate text-[14px] font-bold text-ink">{goal.name}</p>
          <p className="mt-[3px] text-[11.5px] font-medium text-ink-muted">
            {goal.deadline ? `Prazo em ${formatDate(goal.deadline)}` : 'Sem prazo definido'}
            {goal.account_id !== null ? ' · Vinculada a uma conta' : ''}
          </p>
        </div>

        <div className="flex shrink-0 items-center gap-1">
          <Button variant="ghost" onClick={onNewContribution} disabled={goal.is_archived}>
            <Plus className="size-3.5" aria-hidden="true" />
            Novo aporte
          </Button>
          <Button variant="ghost" onClick={onEdit} aria-label="Editar meta" className="size-8 p-0">
            <Pencil className="size-3.5" aria-hidden="true" />
          </Button>
          <Button variant="ghost" onClick={onArchive} aria-label={goal.is_archived ? 'Reativar meta' : 'Arquivar meta'} className="size-8 p-0">
            {goal.is_archived ? <ArchiveRestore className="size-3.5" aria-hidden="true" /> : <Archive className="size-3.5" aria-hidden="true" />}
          </Button>
          <Button variant="ghost" onClick={onDelete} aria-label="Excluir meta" className="size-8 p-0">
            <Trash2 className="size-3.5" aria-hidden="true" />
          </Button>
        </div>
      </div>

      <div className="mt-3 flex items-baseline justify-between gap-3 text-[12.5px]">
        <span>
          <Money value={goal.current_amount} className="font-semibold text-ink" /> de{' '}
          <Money value={goal.target_amount} className="text-ink-soft" />
        </span>
        <span className="font-bold text-ink-muted">{formatPercent(goal.progress, 0)}</span>
      </div>

      <div className="mt-2 h-[7px] overflow-hidden rounded-[4px] bg-white/[0.06]">
        <div
          className="h-full rounded-[4px] bg-[linear-gradient(90deg,#A07CFF,#35D68A)] transition-[width] duration-500"
          style={{ width: `${width}%` }}
        />
      </div>

      <button
        type="button"
        onClick={() => setIsExpanded((value) => !value)}
        className="mt-3 flex items-center gap-1.5 text-[11.5px] font-semibold text-ink-muted transition-colors hover:text-ink"
      >
        <ChevronDown className={cn('size-3.5 transition-transform', isExpanded && 'rotate-180')} aria-hidden="true" />
        Histórico de aportes
      </button>

      {isExpanded ? (
        <div className="mt-2.5 flex flex-col gap-2 border-t border-hairline pt-2.5">
          {isPending ? (
            <>
              <Skeleton className="h-4 w-full" />
              <Skeleton className="h-4 w-full" />
            </>
          ) : contributions && contributions.length > 0 ? (
            contributions.map((contribution) => (
              <div key={contribution.id} className="flex items-center justify-between gap-3 text-[11.5px]">
                <span className="text-ink-muted">
                  {formatDate(contribution.contributed_at)}
                  {contribution.transaction_id === null ? ' · manual' : ' · transferência'}
                </span>
                <div className="flex items-center gap-2">
                  <Money value={contribution.amount} className="font-semibold text-green-bright" />
                  <button
                    type="button"
                    onClick={() => onDeleteContribution(contribution)}
                    aria-label="Desfazer aporte"
                    className="grid size-6 place-items-center rounded-md text-ink-faint transition-colors hover:bg-white/[0.06] hover:text-orange-light"
                  >
                    <Undo2 className="size-3.5" aria-hidden="true" />
                  </button>
                </div>
              </div>
            ))
          ) : (
            <p className="text-[11.5px] text-ink-muted">Nenhum aporte ainda.</p>
          )}
        </div>
      ) : null}
    </div>
  )
}
