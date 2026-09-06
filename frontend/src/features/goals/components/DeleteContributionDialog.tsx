import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { Money } from '@/components/ui/Money'
import type { GoalContribution } from '@/types/api'

interface Props {
  contribution: GoalContribution | null
  isDeleting: boolean
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Desfazer um aporte com transferencia por tras desfaz a transferencia junto
 * — o dinheiro volta para a conta de origem. Vale um aviso explicito, porque
 * essa parte mexe em saldo de verdade.
 */
export function DeleteContributionDialog({ contribution, isDeleting, onCancel, onConfirm }: Props) {
  const isLinked = contribution?.transaction_id !== null

  return (
    <Modal
      isOpen={contribution !== null}
      onClose={onCancel}
      title="Desfazer aporte"
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
            Desfazer
          </Button>
        </>
      }
    >
      {contribution ? (
        <div className="flex gap-3.5">
          <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-danger/[0.12] text-danger">
            <AlertTriangle className="size-4" aria-hidden="true" />
          </span>
          <p className="text-[12px] leading-relaxed text-ink-soft">
            O aporte de <Money value={contribution.amount} className="font-semibold text-ink" /> sai
            do progresso da meta.{' '}
            {isLinked
              ? 'Ele veio de uma transferência real — ela e o dinheiro voltam para a conta de origem.'
              : 'Era só um registro manual, sem transferência por trás.'}
          </p>
        </div>
      ) : null}
    </Modal>
  )
}
