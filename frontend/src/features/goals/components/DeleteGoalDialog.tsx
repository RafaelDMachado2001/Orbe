import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import type { GoalRecord } from '@/types/api'

interface Props {
  goal: GoalRecord | null
  isDeleting: boolean
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Excluir a meta leva o historico de aportes junto, mas nao desfaz nenhuma
 * transferencia real que os aportes ja fizeram — o dinheiro continua onde
 * foi parar, so a meta para de acompanhar.
 */
export function DeleteGoalDialog({ goal, isDeleting, onCancel, onConfirm }: Props) {
  return (
    <Modal
      isOpen={goal !== null}
      onClose={onCancel}
      title="Excluir meta"
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
      {goal ? (
        <div className="flex gap-3.5">
          <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-danger/[0.12] text-danger">
            <AlertTriangle className="size-4" aria-hidden="true" />
          </span>
          <p className="text-[12px] leading-relaxed text-ink-soft">
            <strong className="font-semibold text-ink">{goal.name}</strong> e todo o histórico de
            aportes somem. As transferências que os aportes já fizeram continuam no extrato — o
            dinheiro não volta, só a meta para de ser acompanhada.
          </p>
        </div>
      ) : null}
    </Modal>
  )
}
