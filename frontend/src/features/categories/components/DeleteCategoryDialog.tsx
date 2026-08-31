import { AlertTriangle } from 'lucide-react'
import { useEffect, useState } from 'react'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { Select } from '@/components/ui/Select'
import type { CategoryNode } from '@/types/api'

interface Props {
  category: CategoryNode | null
  /** Todas as categorias do mesmo tipo, achatadas, menos a que vai sumir. */
  destinations: { value: string; label: string }[]
  isDeleting: boolean
  error: string | null
  onCancel: () => void
  onConfirm: (reassignTo: number | null) => void
}

/**
 * Excluir uma categoria em uso deixaria lançamentos sem classificação e um
 * buraco no relatório do mês. Por isso o diálogo não pergunta "tem certeza?" —
 * ele pergunta para onde vai o que estava classificado ali.
 */
export function DeleteCategoryDialog({
  category,
  destinations,
  isDeleting,
  error,
  onCancel,
  onConfirm,
}: Props) {
  const [destination, setDestination] = useState('')

  useEffect(() => {
    setDestination('')
  }, [category])

  const entries = category?.entries_count ?? 0
  const children = category?.children.length ?? 0
  const needsDestination = entries > 0

  return (
    <Modal
      isOpen={category !== null}
      onClose={onCancel}
      title="Excluir categoria"
      className="max-w-[460px]"
      footer={
        <>
          <Button variant="ghost" onClick={onCancel}>
            Manter
          </Button>
          <Button
            onClick={() => onConfirm(destination === '' ? null : Number(destination))}
            isLoading={isDeleting}
            disabled={needsDestination && destination === ''}
            className="bg-danger text-white shadow-[0_6px_20px_rgba(244,81,95,0.24)] hover:bg-danger/85"
          >
            Excluir
          </Button>
        </>
      }
    >
      {category ? (
        <div className="flex flex-col gap-4">
          <div className="flex gap-3.5">
            <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-danger/[0.12] text-danger">
              <AlertTriangle className="size-4" aria-hidden="true" />
            </span>
            <div className="flex flex-col gap-2">
              <p className="text-[13px] font-semibold text-ink">{category.name}</p>

              <p className="text-[12px] leading-relaxed text-ink-soft">
                {entries === 0
                  ? 'Nenhum lançamento usa esta categoria — ela some sem deixar rastro.'
                  : `${entries} ${entries === 1 ? 'registro está classificado' : 'registros estão classificados'} aqui. Escolha para onde levá-${entries === 1 ? 'lo' : 'los'}: nada é excluído junto.`}
              </p>

              {children > 0 ? (
                <p className="text-[11.5px] leading-relaxed text-ink-muted">
                  {children === 1 ? 'A subcategoria' : `As ${children} subcategorias`} não
                  {children === 1 ? ' é excluída' : ' são excluídas'}: passam para a categoria de
                  destino.
                </p>
              ) : null}
            </div>
          </div>

          <Select
            label={needsDestination ? 'Mover para' : 'Mover para (opcional)'}
            placeholder={needsDestination ? 'Escolha a categoria' : 'Não mover nada'}
            options={destinations}
            value={destination}
            onChange={(event) => setDestination(event.target.value)}
          />

          {error ? (
            <p
              role="alert"
              className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] text-orange-light"
            >
              {error}
            </p>
          ) : null}
        </div>
      ) : null}
    </Modal>
  )
}
