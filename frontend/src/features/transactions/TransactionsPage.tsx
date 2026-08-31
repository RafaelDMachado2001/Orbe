import { ChevronLeft, ChevronRight, Plus } from 'lucide-react'
import { format } from 'date-fns'
import { useState } from 'react'

import { useToast } from '@/app/useToast'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { ErrorState } from '@/components/ui/States'
import { Toast } from '@/components/ui/Toast'
import { apiErrorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import type { TransactionEntry, TransactionStatus } from '@/types/api'

import {
  useChangeTransactionStatus,
  useDeleteTransaction,
  useTransactionOptions,
  useTransactions,
} from './api'
import { DeleteDialog } from './components/DeleteDialog'
import { FilterBar } from './components/FilterBar'
import { SummaryStrip, SummaryStripSkeleton } from './components/SummaryStrip'
import { TransactionFormModal } from './components/TransactionFormModal'
import { TransactionsTable, TransactionsTableSkeleton } from './components/TransactionsTable'
import { useTransactionFilters } from './useTransactionFilters'

/**
 * Extrato de lançamentos.
 *
 * Filtrar, paginar e trocar o período mudam apenas o estado em memória: a
 * consulta se refaz e a tela se recompõe no lugar, mantendo a lista anterior
 * visível enquanto a nova chega. Nada aqui navega nem recarrega a página.
 */
export function TransactionsPage() {
  const controller = useTransactionFilters()
  const { filters } = controller

  const { data, isPending, isFetching, isError, error, refetch } = useTransactions(filters)
  const { data: options } = useTransactionOptions()

  const changeStatus = useChangeTransactionStatus()
  const remove = useDeleteTransaction()

  const [editingId, setEditingId] = useState<number | null>(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [pendingDeletion, setPendingDeletion] = useState<TransactionEntry | null>(null)
  const { toast, notify, dismiss: dismissToast } = useToast()

  // Só o primeiro carregamento mostra esqueleto; a partir daí a tela troca o
  // conteúdo no lugar, com o anterior esmaecido.
  const isRefreshing = isFetching && !isPending

  function openCreate() {
    setEditingId(null)
    setIsFormOpen(true)
  }

  function openEdit(entry: TransactionEntry) {
    setEditingId(entry.transaction_id)
    setIsFormOpen(true)
  }

  async function handleStatus(entry: TransactionEntry, status: TransactionStatus) {
    try {
      await changeStatus.mutateAsync({ id: entry.transaction_id, status })
      notify(statusMessage(status))
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível mudar a situação.'), 'erro')
    }
  }

  async function handleDelete() {
    if (pendingDeletion === null) {
      return
    }

    try {
      await remove.mutateAsync(pendingDeletion.transaction_id)
      notify('Lançamento excluído.')
      setPendingDeletion(null)
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível excluir o lançamento.'), 'erro')
      setPendingDeletion(null)
    }
  }

  const meta = data?.meta
  const firstRow = meta && meta.total > 0 ? (meta.page - 1) * meta.per_page + 1 : 0
  const lastRow = meta ? Math.min(meta.page * meta.per_page, meta.total) : 0

  return (
    <AppShell cardsCount={options?.cards.length}>
      <header className="flex flex-wrap items-center gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-[22px] font-bold tracking-[-0.4px]">Lançamentos</h1>
          <p className="text-[12.5px] font-medium text-ink-dim">
            {data
              ? `${data.summary.entries} ${data.summary.entries === 1 ? 'lançamento' : 'lançamentos'} no período · parcelas de cartão aparecem uma a uma`
              : 'Carregando o extrato…'}
          </p>
        </div>

        <div className="ml-auto flex items-center gap-2.5">
          <Button variant="ghost" disabled title="Disponível na próxima entrega">
            Exportar
          </Button>
          <Button onClick={openCreate}>
            <Plus className="size-3.5" aria-hidden="true" />
            Novo lançamento
          </Button>
        </div>
      </header>

      <FilterBar controller={controller} options={options} />

      {isError ? (
        <ErrorState
          description={apiErrorMessage(error, 'Não foi possível carregar os lançamentos.')}
          onRetry={() => void refetch()}
        />
      ) : null}

      {isPending ? (
        <>
          <SummaryStripSkeleton />
          <TransactionsTableSkeleton />
        </>
      ) : null}

      {data ? (
        <>
          <SummaryStrip
            summary={data.summary}
            from={filters.from}
            to={filters.to}
            isRefreshing={isRefreshing}
          />

          <Card className="relative overflow-hidden p-[20px_22px]">
            {/* Fita de progresso: mostra que a lista está sendo refeita sem
                tirar da tela o que já está lá. */}
            <span
              aria-hidden="true"
              className={cn(
                'absolute inset-x-0 top-0 h-[2px] origin-left bg-[linear-gradient(90deg,#35D68A,#A07CFF)] transition-opacity',
                isRefreshing ? 'animate-pulse opacity-100' : 'opacity-0',
              )}
            />

            <TransactionsTable
              entries={data.items}
              isRefreshing={isRefreshing}
              onEdit={openEdit}
              onDelete={setPendingDeletion}
              onChangeStatus={(entry, status) => void handleStatus(entry, status)}
              emptyAction={
                <Button variant="ghost" onClick={openCreate}>
                  <Plus className="size-3.5" aria-hidden="true" />
                  Novo lançamento
                </Button>
              }
            />

            {meta && meta.total > 0 ? (
              <div className="mt-4 flex flex-wrap items-center gap-3 border-t border-hairline pt-3.5">
                <p className="text-[11.5px] font-medium text-ink-muted">
                  Mostrando {firstRow}–{lastRow} de {meta.total}
                </p>

                <div className="ml-auto flex items-center gap-1.5">
                  <PageButton
                    label="Página anterior"
                    disabled={meta.page <= 1}
                    onClick={() => controller.setPage(meta.page - 1)}
                  >
                    <ChevronLeft className="size-3.5" aria-hidden="true" />
                  </PageButton>

                  <span className="px-1 text-[11.5px] font-semibold text-ink-soft">
                    {meta.page} de {meta.last_page}
                  </span>

                  <PageButton
                    label="Próxima página"
                    disabled={!meta.has_more}
                    onClick={() => controller.setPage(meta.page + 1)}
                  >
                    <ChevronRight className="size-3.5" aria-hidden="true" />
                  </PageButton>
                </div>
              </div>
            ) : null}
          </Card>
        </>
      ) : null}

      <TransactionFormModal
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        transactionId={editingId}
        options={options}
        today={format(new Date(), 'yyyy-MM-dd')}
        onSaved={notify}
      />

      <DeleteDialog
        entry={pendingDeletion}
        isDeleting={remove.isPending}
        onCancel={() => setPendingDeletion(null)}
        onConfirm={() => void handleDelete()}
      />

      <Toast toast={toast} onDismiss={dismissToast} />
    </AppShell>
  )
}

function PageButton({
  children,
  label,
  disabled,
  onClick,
}: {
  children: React.ReactNode
  label: string
  disabled: boolean
  onClick: () => void
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={label}
      className="grid size-7 place-items-center rounded-lg border border-hairline text-ink-soft transition-colors hover:border-hairline-strong hover:text-ink disabled:cursor-not-allowed disabled:opacity-40"
    >
      {children}
    </button>
  )
}

function statusMessage(status: TransactionStatus): string {
  switch (status) {
    case 'confirmado':
      return 'Lançamento confirmado.'
    case 'previsto':
      return 'Lançamento voltou para previsto.'
    default:
      return 'Lançamento cancelado.'
  }
}
