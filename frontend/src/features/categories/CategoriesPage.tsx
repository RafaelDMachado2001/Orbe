import { format } from 'date-fns'
import { Plus } from 'lucide-react'
import { useMemo, useState } from 'react'

import { useToast } from '@/app/useToast'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/Button'
import { MonthNav } from '@/components/ui/MonthNav'
import { ErrorState } from '@/components/ui/States'
import { Toast } from '@/components/ui/Toast'
import { apiErrorMessage } from '@/lib/api'
import type { CategoryNode, CategoryType } from '@/types/api'

import { useCategories, useDeleteCategory } from './api'
import { CategoriesSummary, CategoriesSummarySkeleton } from './components/CategoriesSummary'
import { CategoryFormModal } from './components/CategoryFormModal'
import { CategoryTree, CategoryTreeSkeleton } from './components/CategoryTree'
import { DeleteCategoryDialog } from './components/DeleteCategoryDialog'

/**
 * Plano de contas.
 *
 * A tela separa despesa de receita porque essa divisão é a que o resto do app
 * usa: o formulário de lançamentos só oferece categorias do tipo da aba, e um
 * relatório de gastos nunca mistura as duas. O valor ao lado de cada categoria
 * é o do mês navegado — é o que responde "para onde o dinheiro foi".
 */
export function CategoriesPage() {
  const [month, setMonth] = useState(() => format(new Date(), 'yyyy-MM'))

  const { data, isPending, isFetching, isError, error, refetch } = useCategories(month)
  const remove = useDeleteCategory()

  const { toast, notify, dismiss } = useToast()

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editing, setEditing] = useState<CategoryNode | null>(null)
  const [defaultParent, setDefaultParent] = useState<CategoryNode | null>(null)
  const [defaultType, setDefaultType] = useState<CategoryType>('despesa')
  const [pendingDeletion, setPendingDeletion] = useState<CategoryNode | null>(null)
  const [deleteError, setDeleteError] = useState<string | null>(null)

  const isRefreshing = isFetching && !isPending

  const { expenses, incomes } = useMemo(() => {
    const nodes = data?.categories ?? []

    return {
      expenses: nodes.filter((node) => node.type === 'despesa'),
      incomes: nodes.filter((node) => node.type === 'receita'),
    }
  }, [data])

  /**
   * Destinos possíveis ao excluir: qualquer categoria do mesmo tipo, mãe ou
   * filha, menos a própria. A subcategoria aparece com o nome da mãe na
   * frente, senão duas "Aluguel" de árvores diferentes ficariam indistintas.
   */
  const destinations = useMemo(() => {
    if (pendingDeletion === null) {
      return []
    }

    const roots = pendingDeletion.type === 'despesa' ? expenses : incomes
    const options: { value: string; label: string }[] = []

    roots.forEach((root) => {
      if (root.id !== pendingDeletion.id) {
        options.push({ value: root.id.toString(), label: root.name })
      }

      root.children.forEach((child) => {
        if (child.id !== pendingDeletion.id) {
          options.push({ value: child.id.toString(), label: `${root.name} › ${child.name}` })
        }
      })
    })

    return options
  }, [pendingDeletion, expenses, incomes])

  function openCreate(type: CategoryType) {
    setEditing(null)
    setDefaultParent(null)
    setDefaultType(type)
    setIsFormOpen(true)
  }

  function openChild(parent: CategoryNode) {
    setEditing(null)
    setDefaultParent(parent)
    setDefaultType(parent.type)
    setIsFormOpen(true)
  }

  function openEdit(node: CategoryNode) {
    setEditing(node)
    setDefaultParent(null)
    setIsFormOpen(true)
  }

  function openDelete(node: CategoryNode) {
    setDeleteError(null)
    setPendingDeletion(node)
  }

  async function handleDelete(reassignTo: number | null) {
    if (pendingDeletion === null) {
      return
    }

    setDeleteError(null)

    try {
      await remove.mutateAsync({ id: pendingDeletion.id, reassignTo })
      notify('Categoria excluída.')
      setPendingDeletion(null)
    } catch (mutationError) {
      // O erro fica dentro do diálogo: fechar levaria embora a explicação de
      // por que a exclusão não passou.
      setDeleteError(apiErrorMessage(mutationError, 'Não foi possível excluir a categoria.'))
    }
  }

  return (
    <AppShell>
      <header className="flex flex-wrap items-center gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-[22px] font-bold tracking-[-0.4px]">Categorias</h1>
          <p className="text-[12.5px] font-medium text-ink-dim">
            {data
              ? `${data.summary.total} categorias · o valor ao lado é o do mês navegado`
              : 'Carregando o plano de contas…'}
          </p>
        </div>

        <div className="ml-auto flex flex-wrap items-center gap-2.5">
          <MonthNav month={month} onChange={setMonth} />

          <Button onClick={() => openCreate('despesa')}>
            <Plus className="size-3.5" aria-hidden="true" />
            Nova categoria
          </Button>
        </div>
      </header>

      {isError ? (
        <ErrorState
          description={apiErrorMessage(error, 'Não foi possível carregar as categorias.')}
          onRetry={() => void refetch()}
        />
      ) : null}

      {isPending ? (
        <>
          <CategoriesSummarySkeleton />
          <CategoryTreeSkeleton />
        </>
      ) : null}

      {data ? (
        <>
          <CategoriesSummary summary={data.summary} isRefreshing={isRefreshing} />

          <CategoryTree
            title="Despesas"
            subtitle="Classificam tudo o que sai — na conta e no cartão"
            nodes={expenses}
            emptyDescription="Crie categorias como Moradia, Alimentação e Transporte para enxergar para onde o dinheiro vai."
            isRefreshing={isRefreshing}
            onEdit={openEdit}
            onDelete={openDelete}
            onAddChild={openChild}
            action={
              <Button variant="ghost" onClick={() => openCreate('despesa')}>
                <Plus className="size-3.5" aria-hidden="true" />
                Nova
              </Button>
            }
          />

          <CategoryTree
            title="Receitas"
            subtitle="Classificam o que entra e alimentam a previsão"
            nodes={incomes}
            emptyDescription="Salário, serviços e rendimentos separados ajudam a ver de onde a renda vem."
            isRefreshing={isRefreshing}
            onEdit={openEdit}
            onDelete={openDelete}
            onAddChild={openChild}
            action={
              <Button variant="ghost" onClick={() => openCreate('receita')}>
                <Plus className="size-3.5" aria-hidden="true" />
                Nova
              </Button>
            }
          />
        </>
      ) : null}

      <CategoryFormModal
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        category={editing}
        defaultParent={defaultParent}
        defaultType={defaultType}
        roots={[...expenses, ...incomes]}
        types={data?.types ?? []}
        onSaved={notify}
      />

      <DeleteCategoryDialog
        category={pendingDeletion}
        destinations={destinations}
        isDeleting={remove.isPending}
        error={deleteError}
        onCancel={() => setPendingDeletion(null)}
        onConfirm={(reassignTo) => void handleDelete(reassignTo)}
      />

      <Toast toast={toast} onDismiss={dismiss} />
    </AppShell>
  )
}
