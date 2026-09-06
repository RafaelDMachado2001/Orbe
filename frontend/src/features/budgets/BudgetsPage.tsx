import { format } from 'date-fns'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'

import { useToast } from '@/app/useToast'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader } from '@/components/ui/Card'
import { MonthNav } from '@/components/ui/MonthNav'
import { Money } from '@/components/ui/Money'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { Toast } from '@/components/ui/Toast'
import { apiErrorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import { formatPercent } from '@/lib/format'
import type { BudgetStatus } from '@/types/api'

import { useBudgets, useDeleteBudget } from './api'
import { BudgetFormModal } from './components/BudgetFormModal'
import { DeleteBudgetDialog } from './components/DeleteBudgetDialog'

/**
 * Orcamentos: um limite de gasto por categoria de despesa, por mes. O status
 * de cada um (gasto real, percentual, estouro) vem pronto do endpoint — a
 * mesma conta que already alimenta o alerta de orcamento estourado no
 * dashboard.
 */
export function BudgetsPage() {
  const [month, setMonth] = useState(() => format(new Date(), 'yyyy-MM'))

  const { data, isPending, isFetching, isError, error, refetch } = useBudgets(month)
  const deleteBudget = useDeleteBudget()

  const { toast, notify, dismiss } = useToast()

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingBudget, setEditingBudget] = useState<BudgetStatus | null>(null)
  const [deletingBudget, setDeletingBudget] = useState<BudgetStatus | null>(null)

  function openCreateForm() {
    setEditingBudget(null)
    setIsFormOpen(true)
  }

  function openEditForm(budget: BudgetStatus) {
    setEditingBudget(budget)
    setIsFormOpen(true)
  }

  async function confirmDelete() {
    if (!deletingBudget) {
      return
    }

    try {
      await deleteBudget.mutateAsync(deletingBudget.id)
      setDeletingBudget(null)
      notify('Orçamento excluído.')
    } catch (error) {
      notify(apiErrorMessage(error, 'Não foi possível excluir o orçamento.'))
    }
  }

  return (
    <AppShell>
      <header className="flex flex-wrap items-center gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-[22px] font-bold tracking-[-0.4px]">Orçamentos</h1>
          <p className="text-[12.5px] font-medium text-ink-dim">Limites de gasto por categoria</p>
        </div>

        <div className="ml-auto flex items-center gap-2.5">
          <MonthNav month={month} onChange={setMonth} />
          <Button onClick={openCreateForm}>
            <Plus className="size-3.5" aria-hidden="true" />
            Novo orçamento
          </Button>
        </div>
      </header>

      {isError ? (
        <ErrorState
          description={apiErrorMessage(error, 'Não foi possível carregar os orçamentos.')}
          onRetry={() => void refetch()}
        />
      ) : null}

      {isPending ? <BudgetListSkeleton /> : null}

      {data ? (
        <Card aria-busy={isFetching}>
          <CardHeader title="Limites do mês" subtitle={`${data.length} ${data.length === 1 ? 'categoria' : 'categorias'} acompanhadas`} className="mb-[18px]" />

          {data.length === 0 ? (
            <EmptyState
              title="Nenhum orçamento neste mês"
              description="Defina um limite por categoria para acompanhar o gasto e receber alerta quando estourar."
              action={
                <Button variant="ghost" onClick={openCreateForm}>
                  <Plus className="size-3.5" aria-hidden="true" />
                  Novo orçamento
                </Button>
              }
            />
          ) : (
            <div className="flex flex-col gap-4">
              {data.map((budget) => (
                <BudgetRow
                  key={budget.id}
                  budget={budget}
                  onEdit={() => openEditForm(budget)}
                  onDelete={() => setDeletingBudget(budget)}
                />
              ))}
            </div>
          )}
        </Card>
      ) : null}

      <BudgetFormModal
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        budget={editingBudget}
        month={month}
        onSaved={notify}
      />

      <DeleteBudgetDialog
        budget={deletingBudget}
        isDeleting={deleteBudget.isPending}
        onCancel={() => setDeletingBudget(null)}
        onConfirm={() => void confirmDelete()}
      />

      <Toast toast={toast} onDismiss={dismiss} />
    </AppShell>
  )
}

function BudgetRow({
  budget,
  onEdit,
  onDelete,
}: {
  budget: BudgetStatus
  onEdit: () => void
  onDelete: () => void
}) {
  const width = Math.min(budget.percentage, 100)

  return (
    <div className="rounded-[14px] border border-hairline bg-surface-alt p-[14px_16px]">
      <div className="flex items-center gap-3">
        <span className="size-3 shrink-0 rounded-full" style={{ backgroundColor: budget.color }} aria-hidden="true" />

        <div className="min-w-0 flex-1">
          <p className="truncate text-[13px] font-semibold text-ink">{budget.category}</p>
          <p className="text-[11.5px] font-medium text-ink-muted">
            <Money value={budget.spent} className="text-ink-soft" /> de <Money value={budget.limit_amount} />
          </p>
        </div>

        <span
          className={cn(
            'shrink-0 rounded-md px-[7px] py-0.5 text-[11px] font-bold',
            budget.is_exceeded ? 'bg-orange/15 text-orange-light' : 'bg-white/[0.07] text-ink-muted',
          )}
        >
          {formatPercent(budget.percentage, 0)}
        </span>

        <div className="flex shrink-0 items-center gap-1">
          <Button variant="ghost" onClick={onEdit} aria-label="Editar orçamento" className="size-8 p-0">
            <Pencil className="size-3.5" aria-hidden="true" />
          </Button>
          <Button variant="ghost" onClick={onDelete} aria-label="Excluir orçamento" className="size-8 p-0">
            <Trash2 className="size-3.5" aria-hidden="true" />
          </Button>
        </div>
      </div>

      <div className="mt-[10px] h-[6px] overflow-hidden rounded-[4px] bg-white/[0.06]">
        <div
          className={cn('h-full rounded-[4px] transition-[width] duration-500', budget.is_exceeded && 'bg-orange')}
          style={{ width: `${width}%`, background: budget.is_exceeded ? undefined : budget.color }}
        />
      </div>
    </div>
  )
}

function BudgetListSkeleton() {
  return (
    <Card>
      <Skeleton className="h-4 w-32" />
      <div className="mt-[18px] flex flex-col gap-4">
        {Array.from({ length: 3 }).map((_, index) => (
          <div key={index} className="rounded-[14px] border border-hairline bg-surface-alt p-[14px_16px]">
            <div className="flex items-center gap-3">
              <Skeleton className="size-3 rounded-full" />
              <Skeleton className="h-4 flex-1" />
              <Skeleton className="h-4 w-10" />
            </div>
            <Skeleton className="mt-[10px] h-[6px] w-full" />
          </div>
        ))}
      </div>
    </Card>
  )
}
