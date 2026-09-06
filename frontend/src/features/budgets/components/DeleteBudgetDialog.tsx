import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import type { BudgetStatus } from '@/types/api'

interface Props {
  budget: BudgetStatus | null
  isDeleting: boolean
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Orcamento e so um limite: excluir nao apaga nenhum lancamento, so para de
 * acompanhar aquela categoria no mes. Por isso o dialogo e uma confirmacao
 * simples, sem contagem de dependencias como em conta ou cartao.
 */
export function DeleteBudgetDialog({ budget, isDeleting, onCancel, onConfirm }: Props) {
  return (
    <Modal
      isOpen={budget !== null}
      onClose={onCancel}
      title="Excluir orçamento"
      className="max-w-[420px]"
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
      {budget ? (
        <div className="flex gap-3.5">
          <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-danger/[0.12] text-danger">
            <AlertTriangle className="size-4" aria-hidden="true" />
          </span>
          <p className="text-[12px] leading-relaxed text-ink-soft">
            O limite de <strong className="font-semibold text-ink">{budget.category}</strong> deixa de
            ser acompanhado neste mês. Os lançamentos já feitos nessa categoria não são afetados.
          </p>
        </div>
      ) : null}
    </Modal>
  )
}
