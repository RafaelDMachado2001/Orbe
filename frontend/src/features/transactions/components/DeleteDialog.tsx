import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { formatBRL } from '@/lib/format'
import type { TransactionEntry } from '@/types/api'

interface Props {
  entry: TransactionEntry | null
  isDeleting: boolean
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Excluir alcança mais do que a linha clicada: uma parcela leva a compra
 * inteira e uma transferência leva o par. O diálogo diz o que some antes de
 * sumir — desfazer não existe.
 */
export function DeleteDialog({ entry, isDeleting, onCancel, onConfirm }: Props) {
  return (
    <Modal
      isOpen={entry !== null}
      onClose={onCancel}
      title="Excluir lançamento"
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
      {entry ? (
        <div className="flex gap-3.5">
          <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-danger/[0.12] text-danger">
            <AlertTriangle className="size-4" aria-hidden="true" />
          </span>
          <div className="flex flex-col gap-2">
            <p className="text-[13px] font-semibold text-ink">{entry.description}</p>
            <p className="text-[12px] leading-relaxed text-ink-soft">
              {consequence(entry)}
            </p>
            <p className="text-[11.5px] text-ink-muted">
              {formatBRL(entry.amount)} · {entry.source}
            </p>
          </div>
        </div>
      ) : null}
    </Modal>
  )
}

function consequence(entry: TransactionEntry): string {
  if (entry.origin === 'cartao') {
    return `Esta é a parcela ${entry.installment}. Excluir remove a compra inteira, com todas as parcelas, e devolve o valor às faturas em que elas estavam.`
  }

  if (entry.is_transfer) {
    return 'Transferências existem em dois lados. Excluir remove os dois e devolve o saldo às contas de origem e destino.'
  }

  return 'O lançamento sai do extrato e o saldo da conta é recalculado. Esta ação não pode ser desfeita.'
}
