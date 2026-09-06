import { Plus } from 'lucide-react'
import { useState } from 'react'

import { useToast } from '@/app/useToast'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { Toast } from '@/components/ui/Toast'
import { apiErrorMessage } from '@/lib/api'
import type { GoalContribution, GoalRecord } from '@/types/api'

import { useArchiveGoal, useDeleteContribution, useDeleteGoal, useGoals } from './api'
import { ContributionModal } from './components/ContributionModal'
import { DeleteContributionDialog } from './components/DeleteContributionDialog'
import { DeleteGoalDialog } from './components/DeleteGoalDialog'
import { GoalCard } from './components/GoalCard'
import { GoalFormModal } from './components/GoalFormModal'

/**
 * Metas financeiras. O progresso de cada uma vem do valor inicial informado
 * na criacao mais a soma dos aportes — nunca de um numero editado direto.
 */
export function GoalsPage() {
  const { data, isPending, isFetching, isError, error, refetch } = useGoals()
  const archiveGoal = useArchiveGoal()
  const deleteGoal = useDeleteGoal()
  const deleteContribution = useDeleteContribution()

  const { toast, notify, dismiss } = useToast()

  const [showArchived, setShowArchived] = useState(false)

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingGoal, setEditingGoal] = useState<GoalRecord | null>(null)

  const [contributingGoal, setContributingGoal] = useState<GoalRecord | null>(null)
  const [deletingGoal, setDeletingGoal] = useState<GoalRecord | null>(null)
  const [deletingContribution, setDeletingContribution] = useState<{
    goal: GoalRecord
    contribution: GoalContribution
  } | null>(null)

  function openCreateForm() {
    setEditingGoal(null)
    setIsFormOpen(true)
  }

  async function toggleArchive(goal: GoalRecord) {
    try {
      await archiveGoal.mutateAsync({ id: goal.id, isArchived: !goal.is_archived })
      notify(goal.is_archived ? 'Meta reativada.' : 'Meta arquivada.')
    } catch (error) {
      notify(apiErrorMessage(error, 'Não foi possível atualizar a meta.'))
    }
  }

  async function confirmDeleteGoal() {
    if (!deletingGoal) {
      return
    }

    try {
      await deleteGoal.mutateAsync(deletingGoal.id)
      setDeletingGoal(null)
      notify('Meta excluída.')
    } catch (error) {
      notify(apiErrorMessage(error, 'Não foi possível excluir a meta.'))
    }
  }

  async function confirmDeleteContribution() {
    if (!deletingContribution) {
      return
    }

    try {
      await deleteContribution.mutateAsync({
        goalId: deletingContribution.goal.id,
        contributionId: deletingContribution.contribution.id,
      })
      setDeletingContribution(null)
      notify('Aporte desfeito.')
    } catch (error) {
      notify(apiErrorMessage(error, 'Não foi possível desfazer o aporte.'))
    }
  }

  const goals = (data ?? []).filter((goal) => showArchived || !goal.is_archived)

  return (
    <AppShell>
      <header className="flex flex-wrap items-center gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-[22px] font-bold tracking-[-0.4px]">Metas</h1>
          <p className="text-[12.5px] font-medium text-ink-dim">Acompanhe seus objetivos financeiros</p>
        </div>

        <div className="ml-auto flex items-center gap-2.5">
          <Button
            variant="ghost"
            onClick={() => setShowArchived((value) => !value)}
            className={showArchived ? 'border-purple/40 text-purple-light' : undefined}
          >
            {showArchived ? 'Ocultar arquivadas' : 'Mostrar arquivadas'}
          </Button>
          <Button onClick={openCreateForm}>
            <Plus className="size-3.5" aria-hidden="true" />
            Nova meta
          </Button>
        </div>
      </header>

      {isError ? (
        <ErrorState
          description={apiErrorMessage(error, 'Não foi possível carregar as metas.')}
          onRetry={() => void refetch()}
        />
      ) : null}

      {isPending ? <GoalListSkeleton /> : null}

      {data ? (
        <Card aria-busy={isFetching}>
          <CardHeader title="Suas metas" subtitle={`${goals.length} ${goals.length === 1 ? 'meta' : 'metas'}`} className="mb-[18px]" />

          {goals.length === 0 ? (
            <EmptyState
              title="Nenhuma meta por aqui"
              description="Defina um valor alvo e acompanhe o progresso por aporte, com ou sem conta vinculada."
              action={
                <Button variant="ghost" onClick={openCreateForm}>
                  <Plus className="size-3.5" aria-hidden="true" />
                  Nova meta
                </Button>
              }
            />
          ) : (
            <div className="flex flex-col gap-3">
              {goals.map((goal) => (
                <GoalCard
                  key={goal.id}
                  goal={goal}
                  onNewContribution={() => setContributingGoal(goal)}
                  onEdit={() => {
                    setEditingGoal(goal)
                    setIsFormOpen(true)
                  }}
                  onArchive={() => void toggleArchive(goal)}
                  onDelete={() => setDeletingGoal(goal)}
                  onDeleteContribution={(contribution) => setDeletingContribution({ goal, contribution })}
                />
              ))}
            </div>
          )}
        </Card>
      ) : null}

      <GoalFormModal isOpen={isFormOpen} onClose={() => setIsFormOpen(false)} goal={editingGoal} onSaved={notify} />

      <ContributionModal
        isOpen={contributingGoal !== null}
        onClose={() => setContributingGoal(null)}
        goal={contributingGoal}
        onSaved={notify}
      />

      <DeleteGoalDialog
        goal={deletingGoal}
        isDeleting={deleteGoal.isPending}
        onCancel={() => setDeletingGoal(null)}
        onConfirm={() => void confirmDeleteGoal()}
      />

      <DeleteContributionDialog
        contribution={deletingContribution?.contribution ?? null}
        isDeleting={deleteContribution.isPending}
        onCancel={() => setDeletingContribution(null)}
        onConfirm={() => void confirmDeleteContribution()}
      />

      <Toast toast={toast} onDismiss={dismiss} />
    </AppShell>
  )
}

function GoalListSkeleton() {
  return (
    <Card>
      <Skeleton className="h-4 w-24" />
      <div className="mt-[18px] flex flex-col gap-3">
        {Array.from({ length: 2 }).map((_, index) => (
          <div key={index} className="rounded-[16px] border border-hairline bg-surface-alt p-[16px_18px]">
            <div className="flex items-center gap-3">
              <Skeleton className="h-4 flex-1" />
              <Skeleton className="h-8 w-24" />
            </div>
            <Skeleton className="mt-3 h-[7px] w-full" />
          </div>
        ))}
      </div>
    </Card>
  )
}
