import { Upload } from 'lucide-react'
import { useMemo, useState } from 'react'

import { useToast } from '@/app/useToast'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/Button'
import { Card, CardHeader } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { ErrorState } from '@/components/ui/States'
import { Toast } from '@/components/ui/Toast'
import { useTransactionOptions } from '@/features/transactions/api'
import { apiErrorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import type { CsvMapping, ImportBatch, ImportPreview } from '@/types/api'

import { useCommitImport, useImportHistory, usePreviewImport, useUndoImport } from './api'
import { ColumnMapping } from './components/ColumnMapping'
import { ImportHistory } from './components/ImportHistory'
import { PreviewSummary } from './components/PreviewSummary'
import { PreviewTable } from './components/PreviewTable'
import { UndoImportDialog } from './components/UndoImportDialog'
import { UploadPanel } from './components/UploadPanel'

/** Destino codificado no seletor como `conta:12` ou `cartao:3`. */
interface Destination {
  accountId: number | null
  creditCardId: number | null
}

function parseDestination(value: string): Destination | null {
  const [kind, id] = value.split(':')

  if (kind === undefined || id === undefined || Number.isNaN(Number(id))) {
    return null
  }

  return kind === 'cartao'
    ? { accountId: null, creditCardId: Number(id) }
    : { accountId: Number(id), creditCardId: null }
}

/**
 * Importar histórico financeiro.
 *
 * O fluxo tem dois passos e um deles não grava nada: enviar o arquivo só o lê,
 * e a escrita acontece no botão do fim, com as linhas que sobraram marcadas.
 * Um extrato traz dezenas de lançamentos de uma vez — descobrir o erro depois
 * custaria excluir um por um.
 *
 * O estado do arquivo vive na memória da tela, não no servidor: releitura com
 * outro mapeamento ou outro destino reenvia o mesmo arquivo, o que evita
 * guardar um rascunho de importação que ninguém sabe quando expira.
 */
export function ImportPage() {
  const [destination, setDestination] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<ImportPreview | null>(null)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [categories, setCategories] = useState<Record<number, number | null>>({})
  const [readError, setReadError] = useState<string | null>(null)
  const [pendingUndo, setPendingUndo] = useState<ImportBatch | null>(null)

  const { data: options } = useTransactionOptions()
  const history = useImportHistory()
  const readFile = usePreviewImport()
  const commit = useCommitImport()
  const undo = useUndoImport()

  const { toast, notify, dismiss } = useToast()

  const selectedRows = useMemo(
    () => (preview?.rows ?? []).filter((row) => row.is_importable && selected.has(row.index)),
    [preview, selected],
  )

  // Os totais do resumo acompanham o que está marcado agora, não o que o
  // arquivo trouxe: desmarcar metade das linhas precisa mudar o número.
  const liveSummary = useMemo(() => {
    if (preview === null) {
      return null
    }

    let income = 0
    let expense = 0

    for (const row of selectedRows) {
      if (row.direction === 'entrada') {
        income += row.amount
      } else {
        expense += row.amount
      }
    }

    return { ...preview.summary, income: Number(income.toFixed(2)), expense: Number(expense.toFixed(2)) }
  }, [preview, selectedRows])

  async function read(nextFile: File | null, nextDestination: string, mapping?: CsvMapping | null) {
    const target = parseDestination(nextDestination)

    if (nextFile === null || target === null) {
      setPreview(null)
      setReadError(null)

      return
    }

    setReadError(null)

    try {
      const result = await readFile.mutateAsync({ file: nextFile, ...target, mapping })

      setPreview(result)
      setSelected(new Set(result.rows.filter((row) => row.selected).map((row) => row.index)))
      setCategories(Object.fromEntries(result.rows.map((row) => [row.index, row.category_id])))
    } catch (error) {
      setPreview(null)
      setReadError(apiErrorMessage(error, 'Não foi possível ler o arquivo.'))
    }
  }

  function handleFile(next: File | null) {
    setFile(next)
    void read(next, destination)
  }

  function handleDestination(next: string) {
    setDestination(next)
    // Trocar o destino muda o que é repetido e o que a fatura recusa, então a
    // conferência precisa ser refeita — não dá para só reaproveitar as linhas.
    void read(file, next)
  }

  function toggle(index: number) {
    setSelected((current) => {
      const next = new Set(current)

      if (!next.delete(index)) {
        next.add(index)
      }

      return next
    })
  }

  function toggleAll(shouldSelect: boolean) {
    setSelected(
      shouldSelect
        ? new Set((preview?.rows ?? []).filter((row) => row.is_importable).map((row) => row.index))
        : new Set(),
    )
  }

  async function handleImport() {
    const target = parseDestination(destination)

    if (preview === null || file === null || target === null || selectedRows.length === 0) {
      return
    }

    try {
      const batch = await commit.mutateAsync({
        filename: file.name,
        format: preview.format,
        account_id: target.accountId,
        credit_card_id: target.creditCardId,
        skipped_count: preview.rows.length - selectedRows.length,
        rows: selectedRows.map((row) => ({
          date: row.date,
          description: row.description,
          amount: row.amount,
          direction: row.direction,
          category_id: categories[row.index] ?? null,
        })),
      })

      setFile(null)
      setPreview(null)
      setSelected(new Set())
      setCategories({})

      notify(
        `${batch.imported_count} ${batch.imported_count === 1 ? 'lançamento importado' : 'lançamentos importados'} para ${batch.destination}.`,
      )
    } catch (error) {
      notify(apiErrorMessage(error, 'Não foi possível importar os lançamentos.'), 'erro')
    }
  }

  async function handleUndo() {
    if (pendingUndo === null) {
      return
    }

    try {
      await undo.mutateAsync(pendingUndo.id)
      notify('Importação desfeita.')
    } catch (error) {
      notify(apiErrorMessage(error, 'Não foi possível desfazer a importação.'), 'erro')
    } finally {
      setPendingUndo(null)
    }
  }

  return (
    <AppShell>
      <header className="flex flex-col gap-1">
        <h1 className="text-[22px] font-bold tracking-[-0.4px]">Importar histórico</h1>
        <p className="text-[12.5px] font-medium text-ink-dim">
          Traga o extrato do banco ou a fatura do cartão em OFX ou CSV. Você confere linha a linha
          antes de qualquer coisa entrar no seu histórico.
        </p>
      </header>

      <section className="grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div className="flex min-w-0 flex-col gap-4">
          <UploadPanel
            options={options}
            destination={destination}
            onDestinationChange={handleDestination}
            file={file}
            onFileChange={handleFile}
            isReading={readFile.isPending}
          />

          {readError !== null ? <ErrorState title="Arquivo não lido" description={readError} /> : null}

          {readFile.isPending && preview === null ? <PreviewSkeleton /> : null}

          {preview !== null && liveSummary !== null ? (
            <Card className="flex flex-col gap-4">
              <CardHeader
                title="Confira antes de importar"
                subtitle={caption(preview)}
                action={
                  <Button
                    onClick={() => void handleImport()}
                    isLoading={commit.isPending}
                    disabled={selectedRows.length === 0}
                  >
                    <Upload className="size-3.5" aria-hidden="true" />
                    Importar {selectedRows.length}
                  </Button>
                }
              />

              <PreviewSummary
                summary={liveSummary}
                selectedCount={selectedRows.length}
                isRefreshing={readFile.isPending}
              />

              {preview.mapping !== null ? (
                <ColumnMapping
                  preview={preview}
                  isReading={readFile.isPending}
                  onChange={(mapping) => void read(file, destination, mapping)}
                />
              ) : null}

              <PreviewTable
                rows={preview.rows}
                selected={selected}
                categories={categories}
                categoryOptions={options?.categories ?? []}
                onToggle={toggle}
                onToggleAll={toggleAll}
                onCategoryChange={(index, categoryId) =>
                  setCategories((current) => ({ ...current, [index]: categoryId }))
                }
              />
            </Card>
          ) : null}
        </div>

        <ImportHistory
          batches={history.data ?? []}
          isPending={history.isPending}
          undoingId={undo.isPending ? (pendingUndo?.id ?? null) : null}
          onUndo={setPendingUndo}
        />
      </section>

      <UndoImportDialog
        batch={pendingUndo}
        isUndoing={undo.isPending}
        onCancel={() => setPendingUndo(null)}
        onConfirm={() => void handleUndo()}
      />

      <Toast toast={toast} onDismiss={dismiss} />
    </AppShell>
  )
}

function caption(preview: ImportPreview): string {
  const format = `${preview.format_label} · ${preview.summary.total} ${preview.summary.total === 1 ? 'lançamento' : 'lançamentos'}`

  if (preview.period_start === null || preview.period_end === null) {
    return format
  }

  return `${format} · ${formatDate(preview.period_start)} a ${formatDate(preview.period_end)}`
}

function PreviewSkeleton() {
  return (
    <Card className="flex flex-col gap-4">
      <Skeleton className="h-4 w-48" />
      <div className="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
        {Array.from({ length: 4 }).map((_, index) => (
          <Skeleton key={index} className="h-[58px] w-full rounded-[13px]" />
        ))}
      </div>
      {Array.from({ length: 6 }).map((_, index) => (
        <Skeleton key={index} className="h-6 w-full" />
      ))}
    </Card>
  )
}
