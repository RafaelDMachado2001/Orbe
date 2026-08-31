import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { formatBRL } from '@/lib/format'
import type { RecurrenceRow } from '@/types/api'

interface Props {
  row: RecurrenceRow | null
  isDeleting: boolean
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Excluir a regra não apaga o que ela já lançou — mas isso não é óbvio, e a
 * dúvida faria alguém pausar em vez de excluir por medo. O diálogo diz o que
 * some e o que fica, e oferece pausar como alternativa.
 */
export function DeleteRecurrenceDialog({ row, isDeleting, onCancel, onConfirm }: Props) {
  return (
    <Modal
      isOpen={row !== null}
      onClose={onCancel}
      title="Excluir despesa fixa"
      className="max-w-[440px]"
      footer={
        <>
          <Button variant="ghost" onClick={onCancel}>
            Manter
          </Button>
          <Button
            onClick={onConfirm}
            isLoading={isDeleting}
            className="bg-danger text-white shadow-[0_6px_20px_rgba(244,81,95,0.24)] hover:bg-danger/85"
          >
            Excluir
          </Button>
        </>
      }
    >
      {row ? (
        <div className="flex gap-3.5">
          <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-danger/[0.12] text-danger">
            <AlertTriangle className="size-4" aria-hidden="true" />
          </span>
          <div className="flex flex-col gap-2">
            <p className="text-[13px] font-semibold text-ink">
              {row.description} · {formatBRL(row.amount)}
            </p>
            <p className="text-[12px] leading-relaxed text-ink-soft">
              A regra deixa de existir e some da projeção. Os lançamentos que ela já gerou
              permanecem no extrato — apenas perdem o selo de recorrente.
            </p>
            <p className="text-[11.5px] leading-relaxed text-ink-muted">
              Se a intenção é só interromper por um tempo, pausar preserva a regra para retomar
              depois.
            </p>
          </div>
        </div>
      ) : null}
    </Modal>
  )
}
