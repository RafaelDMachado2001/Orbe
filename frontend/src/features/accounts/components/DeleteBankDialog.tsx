import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import type { BankGroup } from '@/types/api'

interface Props {
  bank: BankGroup | null
  isDeleting: boolean
  error: string | null
  onCancel: () => void
  onConfirm: () => void
}

/**
 * Banco só sai vazio: com conta ou cartão dentro, apagá-lo levaria em cascata
 * contas, faturas e todo o extrato. O diálogo mostra o que ainda está pendurado
 * nele em vez de deixar a API recusar depois.
 */
export function DeleteBankDialog({ bank, isDeleting, error, onCancel, onConfirm }: Props) {
  const inUse = bank !== null && (bank.accounts_count > 0 || bank.cards_count > 0)

  return (
    <Modal
      isOpen={bank !== null}
      onClose={onCancel}
      title="Excluir banco"
      className="max-w-[440px]"
      footer={
        <>
          <Button variant="ghost" onClick={onCancel}>
            Manter
          </Button>
          <Button
            onClick={onConfirm}
            isLoading={isDeleting}
            disabled={inUse}
            className="bg-danger text-white shadow-[0_6px_20px_rgba(244,81,95,0.24)] hover:bg-danger/85"
          >
            Excluir
          </Button>
        </>
      }
    >
      {bank ? (
        <div className="flex flex-col gap-4">
          <div className="flex gap-3.5">
            <span className="grid size-9 shrink-0 place-items-center rounded-[11px] bg-danger/[0.12] text-danger">
              <AlertTriangle className="size-4" aria-hidden="true" />
            </span>
            <div className="flex flex-col gap-2">
              <p className="text-[13px] font-semibold text-ink">{bank.name}</p>
              <p className="text-[12px] leading-relaxed text-ink-soft">
                {inUse
                  ? `${usageLabel(bank)} ainda ${bank.accounts_count + bank.cards_count === 1 ? 'pertence' : 'pertencem'} a este banco. Mova ou exclua ${bank.accounts_count + bank.cards_count === 1 ? 'esse registro' : 'esses registros'} antes: apagar o banco levaria o extrato junto.`
                  : 'Este banco não tem nenhuma conta nem cartão — ele some sem afetar nada.'}
              </p>
            </div>
          </div>

          {error ? (
            <p
              role="alert"
              className="rounded-[11px] border border-orange/25 bg-orange/[0.07] px-3.5 py-2.5 text-[11.5px] leading-relaxed text-orange-light"
            >
              {error}
            </p>
          ) : null}
        </div>
      ) : null}
    </Modal>
  )
}

function usageLabel(bank: BankGroup): string {
  const parts: string[] = []

  if (bank.accounts_count > 0) {
    parts.push(`${bank.accounts_count} ${bank.accounts_count === 1 ? 'conta' : 'contas'}`)
  }

  if (bank.cards_count > 0) {
    parts.push(`${bank.cards_count} ${bank.cards_count === 1 ? 'cartão' : 'cartões'}`)
  }

  return parts.join(' e ')
}
