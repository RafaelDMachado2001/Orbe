import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import type { ImportBatch } from '@/types/api'

interface Props {
  batch: ImportBatch | null
  isUndoing: boolean
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Desfazer apaga tudo o que o lote criou — inclusive o que foi editado depois.
 * O diálogo diz o número antes do clique, porque a ação não tem volta.
 */
export function UndoImportDialog({ batch, isUndoing, onCancel, onConfirm }: Props) {
  return (
    <Modal
      isOpen={batch !== null}
      onClose={onCancel}
      title="Desfazer importação"
      className="max-w-[440px]"
      footer={
        <>
          <Button variant="ghost" onClick={onCancel}>
            Manter
          </Button>
          <Button
            onClick={onConfirm}
            isLoading={isUndoing}
            className="bg-danger text-white shadow-[0_6px_20px_rgba(244,81,95,0.24)] hover:bg-danger/85"
          >
            Desfazer
          </Button>
        </>
      }
    >
      {batch ? (
        <div className="flex gap-3.5">
          <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-danger/[0.12] text-danger">
            <AlertTriangle className="size-4" aria-hidden="true" />
          </span>
          <div className="flex flex-col gap-2">
            <p className="text-[13px] font-semibold text-ink">{batch.filename}</p>
            <p className="text-[12px] leading-relaxed text-ink-soft">
              Isso remove os {batch.remaining_count} lançamentos que este arquivo trouxe para{' '}
              {batch.destination}, inclusive as alterações feitas neles depois. É tudo ou nada: se
              alguma compra do lote já tiver sido paga junto com a fatura, a operação é recusada.
            </p>
          </div>
        </div>
      ) : null}
    </Modal>
  )
}
