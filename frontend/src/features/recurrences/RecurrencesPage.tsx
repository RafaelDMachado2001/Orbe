import { format } from 'date-fns'
import { Plus, Zap } from 'lucide-react'
import { useMemo, useState } from 'react'

import { useToast } from '@/app/useToast'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/Button'
import { MonthNav } from '@/components/ui/MonthNav'
import { ErrorState } from '@/components/ui/States'
import { Toast } from '@/components/ui/Toast'
import { apiErrorMessage } from '@/lib/api'
import type { RecurrenceRow } from '@/types/api'

import {
  useDeleteRecurrence,
  useLaunchAllRecurrences,
  useLaunchRecurrence,
  useRecurrenceOptions,
  useRecurrences,
  useToggleRecurrence,
} from './api'
import { DeleteRecurrenceDialog } from './components/DeleteRecurrenceDialog'
import { RecurrenceFormModal } from './components/RecurrenceFormModal'
import { RecurrenceList, RecurrenceListSkeleton } from './components/RecurrenceList'
import { RecurrencesSummary, RecurrencesSummarySkeleton } from './components/RecurrencesSummary'

/**
 * Despesas e receitas fixas.
 *
 * A regra e o lançamento são coisas distintas: a regra descreve o que se
 * repete e alimenta a projeção; lançar é o ato de escrever aquele mês no
 * extrato. A tela mostra os dois lados — quanto está comprometido por mês e o
 * que ainda falta lançar.
 */
export function RecurrencesPage() {
  const today = format(new Date(), 'yyyy-MM-dd')
  const [month, setMonth] = useState(() => format(new Date(), 'yyyy-MM'))
  const [showPaused, setShowPaused] = useState(true)

  const { data, isPending, isFetching, isError, error, refetch } = useRecurrences(month, showPaused)
  const { data: options } = useRecurrenceOptions()

  const toggle = useToggleRecurrence()
  const remove = useDeleteRecurrence()
  const launch = useLaunchRecurrence()
  const launchAll = useLaunchAllRecurrences()

  const { toast, notify, dismiss } = useToast()

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [pendingDeletion, setPendingDeletion] = useState<RecurrenceRow | null>(null)
  const [launchingId, setLaunchingId] = useState<number | null>(null)

  const isRefreshing = isFetching && !isPending

  const { expenses, incomes } = useMemo(() => {
    const rows = data?.recurrences ?? []

    return {
      expenses: rows.filter((row) => row.type === 'despesa'),
      incomes: rows.filter((row) => row.type === 'receita'),
    }
  }, [data])

  function openCreate() {
    setEditingId(null)
    setIsFormOpen(true)
  }

  async function handleLaunch(row: RecurrenceRow) {
    setLaunchingId(row.id)

    try {
      const result = await launch.mutateAsync({ id: row.id, month })
      notify(result.message, result.created > 0 ? 'sucesso' : 'erro')
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível lançar a regra.'), 'erro')
    } finally {
      setLaunchingId(null)
    }
  }

  async function handleLaunchAll() {
    try {
      const result = await launchAll.mutateAsync(month)
      notify(result.message, result.created > 0 ? 'sucesso' : 'erro')
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível lançar as regras.'), 'erro')
    }
  }

  async function handleToggle(row: RecurrenceRow, isActive: boolean) {
    try {
      await toggle.mutateAsync({ id: row.id, isActive })
      notify(isActive ? 'Regra retomada.' : 'Regra pausada.')
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível mudar a regra.'), 'erro')
    }
  }

  async function handleDelete() {
    if (pendingDeletion === null) {
      return
    }

    try {
      await remove.mutateAsync(pendingDeletion.id)
      notify('Despesa fixa excluída.')
    } catch (mutationError) {
      notify(apiErrorMessage(mutationError, 'Não foi possível excluir a regra.'), 'erro')
    } finally {
      setPendingDeletion(null)
    }
  }

  const pendingCount = data?.summary.pending_count ?? 0

  return (
    <AppShell>
      <header className="flex flex-wrap items-center gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-[22px] font-bold tracking-[-0.4px]">Despesas fixas</h1>
          <p className="text-[12.5px] font-medium text-ink-dim">
            {data
              ? `${data.summary.active_count} ${data.summary.active_count === 1 ? 'regra ativa' : 'regras ativas'} · a regra alimenta a projeção; lançar escreve no extrato`
              : 'Carregando as regras fixas…'}
          </p>
        </div>

        <div className="ml-auto flex flex-wrap items-center gap-2.5">
          <MonthNav month={month} onChange={setMonth} />

          <Button variant="ghost" onClick={() => setShowPaused((value) => !value)}>
            {showPaused ? 'Ocultar pausadas' : 'Mostrar pausadas'}
          </Button>

          {pendingCount > 0 ? (
            <Button
              variant="ghost"
              onClick={() => void handleLaunchAll()}
              isLoading={launchAll.isPending}
            >
              <Zap className="size-3.5" aria-hidden="true" />
              Lançar {pendingCount} pendente{pendingCount === 1 ? '' : 's'}
            </Button>
          ) : null}

          <Button onClick={openCreate}>
            <Plus className="size-3.5" aria-hidden="true" />
            Nova despesa fixa
          </Button>
        </div>
      </header>

      {isError ? (
        <ErrorState
          description={apiErrorMessage(error, 'Não foi possível carregar as despesas fixas.')}
          onRetry={() => void refetch()}
        />
      ) : null}

      {isPending ? (
        <>
          <RecurrencesSummarySkeleton />
          <RecurrenceListSkeleton />
        </>
      ) : null}

      {data ? (
        <>
          <RecurrencesSummary summary={data.summary} isRefreshing={isRefreshing} />

          <RecurrenceList
            title="Despesas fixas"
            subtitle="O que sai todo mês, na conta ou no cartão"
            rows={expenses}
            emptyDescription="Cadastre aluguel, assinaturas e mensalidades para vê-los na projeção e lançá-los em um clique."
            isRefreshing={isRefreshing}
            launchingId={launchingId}
            onLaunch={(row) => void handleLaunch(row)}
            onEdit={(row) => {
              setEditingId(row.id)
              setIsFormOpen(true)
            }}
            onToggle={(row, isActive) => void handleToggle(row, isActive)}
            onDelete={setPendingDeletion}
            action={
              <Button variant="ghost" onClick={openCreate}>
                <Plus className="size-3.5" aria-hidden="true" />
                Nova
              </Button>
            }
          />

          <RecurrenceList
            title="Receitas fixas"
            subtitle="O que entra todo mês e sustenta a projeção"
            rows={incomes}
            emptyDescription="Salário e outros recebimentos regulares entram aqui e tornam a previsão mais confiável."
            isRefreshing={isRefreshing}
            launchingId={launchingId}
            onLaunch={(row) => void handleLaunch(row)}
            onEdit={(row) => {
              setEditingId(row.id)
              setIsFormOpen(true)
            }}
            onToggle={(row, isActive) => void handleToggle(row, isActive)}
            onDelete={setPendingDeletion}
          />
        </>
      ) : null}

      <RecurrenceFormModal
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        recurrenceId={editingId}
        options={options}
        today={today}
        onSaved={notify}
      />

      <DeleteRecurrenceDialog
        row={pendingDeletion}
        isDeleting={remove.isPending}
        onCancel={() => setPendingDeletion(null)}
        onConfirm={() => void handleDelete()}
      />

      <Toast toast={toast} onDismiss={dismiss} />
    </AppShell>
  )
}
